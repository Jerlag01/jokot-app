<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A member of the youth centre. One row per person; each year they are a member is a Membership.
 * The QR on the member card links to a page (by secret token) where they update their own details.
 */
class Member extends Model
{
    use HasFactory;

    /** The barcode on the pre-printed member cards: exactly 5 digits. */
    public const CARD_PATTERN = '/^\d{5}$/';

    protected $fillable = ['card_number', 'first_name', 'last_name', 'street', 'house_number', 'box', 'postal_code', 'city', 'email', 'phone', 'notes', 'details_updated_at'];

    protected function casts(): array
    {
        return ['details_updated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            $member->token ??= Str::random(40);
            $member->member_number ??= static::nextMemberNumber();
        });
    }

    /**
     * L0001, L0002, ... continuing after the highest number in use.
     */
    public static function nextMemberNumber(): string
    {
        $last = DB::table('members')->whereNotNull('member_number')->lockForUpdate()->orderByDesc('member_number')->value('member_number');

        return 'L'.str_pad((string) ($last ? ((int) substr($last, 1)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Members the POS added straight to the database get their member number and QR token here
     * (scheduled every minute, and when the member list is opened).
     *
     * @return int how many members were completed
     */
    public static function completeMissing(): int
    {
        $ids = static::query()->where(fn (Builder $query) => $query->whereNull('member_number')->orWhereNull('token'))->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $member = static::query()->lockForUpdate()->find($id);

                $member?->forceFill([
                    'member_number' => $member->member_number ?: static::nextMemberNumber(),
                    'token' => $member->token ?: Str::random(40),
                ])->saveQuietly();
            });
        }

        return $ids->count();
    }

    /**
     * Blank becomes null (no card); spaces from a scanner or copy-paste are dropped.
     */
    protected function cardNumber(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value = preg_replace('/\s+/', '', (string) $value)) ? $value : null);
    }

    public static function findByCard(string $cardNumber): ?self
    {
        return static::query()->where('card_number', preg_replace('/\s+/', '', $cardNumber))->first();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->orderByDesc('year');
    }

    public function membershipFor(int $year): HasOne
    {
        return $this->hasOne(Membership::class)->where('year', $year);
    }

    public function scopeMemberIn(Builder $query, int $year): void
    {
        $query->whereHas('memberships', fn (Builder $query) => $query->where('year', $year));
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function address(): ?string
    {
        if (blank($this->street) && blank($this->city)) {
            return null;
        }

        $line = trim("{$this->street} {$this->house_number}".($this->box ? " bus {$this->box}" : ''));

        return trim("{$line}, {$this->postal_code} {$this->city}", ', ');
    }

    public function selfServiceUrl(): string
    {
        if (blank($this->token)) {
            $this->forceFill(['token' => Str::random(40)])->saveQuietly();
        }

        return route('members.edit', $this->token);
    }

    /**
     * A new token: the QR on cards printed before stops working (e.g. a lost card).
     */
    public function regenerateToken(): void
    {
        $this->forceFill(['token' => Str::random(40)])->save();
    }
}
