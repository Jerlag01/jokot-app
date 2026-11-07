<?php

namespace App\Models;

use App\Contracts\HasContract;
use App\Enums\ContractType;
use App\Enums\CupLoanStatus;
use App\Support\AutoContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

/**
 * Lending reusable cups (wisselbekers) in crates. Fees are copied from the settings when the loan is created,
 * so later price changes do not alter running loans.
 */
class CupLoan extends Model implements HasContract
{
    use HasFactory;

    protected $fillable = [
        'status', 'customer_id', 'organisation', 'representative', 'email', 'phone', 'street', 'house_number', 'box',
        'postal_code', 'city', 'vat_number', 'event_name', 'starts_on', 'ends_on', 'cups', 'crates',
        'cups_returned', 'cups_dirty', 'crates_returned', 'crates_damaged', 'rental_fee', 'cup_fee', 'crate_fee',
        'picked_up_at', 'returned_at', 'details', 'admin_notes', 'invoice_id', 'terms_accepted_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'status' => CupLoanStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cups' => 'integer',
            'crates' => 'integer',
            'cups_returned' => 'integer',
            'cups_dirty' => 'integer',
            'crates_returned' => 'integer',
            'crates_damaged' => 'integer',
            'rental_fee' => 'decimal:2',
            'cup_fee' => 'decimal:2',
            'crate_fee' => 'decimal:2',
            'picked_up_at' => 'datetime',
            'returned_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::sendContractOnConfirm();

        static::creating(function (CupLoan $loan): void {
            $loan->status ??= CupLoanStatus::Requested;
            $loan->rental_fee ??= settings()->get('cup_rental_fee');
            $loan->cup_fee ??= settings()->get('cup_fee');
            $loan->crate_fee ??= settings()->get('cup_crate_fee');

            if (! $loan->crates) {
                $loan->crates = (int) ceil($loan->cups / max(1, (int) settings()->get('cups_per_crate')));
            }

            $prefix = 'WB-'.now()->format('Y').'-';
            $last = DB::table('cup_loans')->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->lockForUpdate()->value('reference');
            $loan->reference ??= $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 3, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Confirming sends the contract to sign.
     */
    protected static function sendContractOnConfirm(): void
    {
        static::saved(function (CupLoan $loan): void {
            if ($loan->status === CupLoanStatus::Confirmed && ($loan->wasRecentlyCreated || $loan->wasChanged('status'))) {
                app(AutoContract::class)->onConfirmed($loan);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contract(): MorphOne
    {
        return $this->morphOne(Contract::class, 'contractable')->latestOfMany();
    }

    public function scopeHoldingCups(Builder $query): void
    {
        $query->whereIn('status', CupLoanStatus::holdingCups());
    }

    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from);
    }

    public function address(): string
    {
        $line = trim("{$this->street} {$this->house_number}".($this->box ? " bus {$this->box}" : ''));

        return "{$line}, {$this->postal_code} {$this->city}";
    }

    public function cupsMissing(): int
    {
        return $this->cups_returned === null ? 0 : max(0, $this->cups - $this->cups_returned);
    }

    /**
     * Missing plus dirty cups, each charged at the cup fee (art. 7 of the contract).
     */
    public function cupsCharged(): int
    {
        return $this->cupsMissing() + $this->cups_dirty;
    }

    /**
     * Missing plus damaged crates, each charged at the crate fee.
     */
    public function cratesCharged(): int
    {
        $missing = $this->crates_returned === null ? 0 : max(0, $this->crates - $this->crates_returned);

        return $missing + $this->crates_damaged;
    }

    public function ensureCustomer(): Customer
    {
        if ($this->customer) {
            return $this->customer;
        }

        $customer = Customer::findOrCreateFrom([
            'name' => $this->organisation,
            'contact_name' => $this->representative,
            'vat_number' => $this->vat_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'box' => $this->box,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
        ]);

        $this->customer()->associate($customer)->save();

        return $customer;
    }

    public function contractType(): ContractType
    {
        return ContractType::CupLoan;
    }

    public function contractSignerName(): string
    {
        return $this->representative;
    }

    public function contractSignerEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array<string, string>
     */
    public function contractPlaceholders(): array
    {
        return array_map(fn ($value): string => (string) $value, [
            'referentie' => e($this->reference),
            'verhuurder' => e((string) settings()->get('legal_name')),
            'vertegenwoordiger' => e((string) settings()->get('contract_representative')),
            'huurder' => e($this->organisation),
            'huurder_vertegenwoordiger' => e($this->representative),
            'huurder_adres' => e($this->address()),
            'aantal_bekers' => e((string) $this->cups),
            'aantal_bakken' => e((string) $this->crates),
            'van' => e($this->starts_on->format('d/m/Y')),
            'tot' => e($this->ends_on->format('d/m/Y')),
            'evenement' => e($this->event_name ?? '-'),
            'huurprijs' => e(euro($this->rental_fee)),
            'prijs_beker' => e(euro($this->cup_fee)),
            'prijs_bak' => e(euro($this->crate_fee)),
            'iban' => e((string) settings()->get('iban')),
            'vandaag' => e(now()->format('d/m/Y')),
        ]);
    }
}
