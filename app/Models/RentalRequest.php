<?php

namespace App\Models;

use App\Contracts\HasContract;
use App\Enums\ContractType;
use App\Enums\RentalStatus;
use App\Support\AutoContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

/**
 * A request to rent the hall (huuraanvraag), sent from the public form or entered by the board.
 */
class RentalRequest extends Model implements HasContract
{
    use HasFactory;

    protected $fillable = [
        'status', 'event_date', 'rental_rate_id', 'rental_extra_id',
        'first_name', 'last_name', 'organisation', 'company_number', 'email', 'phone',
        'street', 'house_number', 'box', 'postal_code', 'city',
        'details', 'expected_guests', 'brings_own_wine',
        'quoted_price', 'quoted_deposit', 'admin_notes', 'terms_accepted_at', 'ip_address',
        'customer_id', 'deposit_paid_at', 'corkage_bottles', 'invoice_id', 'drinks_booked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentalStatus::class,
            'event_date' => 'date',
            'brings_own_wine' => 'boolean',
            'expected_guests' => 'integer',
            'quoted_price' => 'decimal:2',
            'quoted_deposit' => 'decimal:2',
            'terms_accepted_at' => 'datetime',
            'deposit_paid_at' => 'datetime',
            'drinks_booked_at' => 'datetime',
            'corkage_bottles' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::sendContractOnConfirm();

        static::creating(function (RentalRequest $request): void {
            $request->status ??= RentalStatus::New;
            $request->reference ??= static::nextReference();

            if ($request->quoted_price === null && $request->rental_rate_id) {
                $rate = RentalRate::query()->find($request->rental_rate_id);
                $extra = $request->rental_extra_id ? RentalExtra::query()->find($request->rental_extra_id) : null;

                $request->quoted_price = (float) $rate?->price + (float) $extra?->price;
                $request->quoted_deposit = (float) $rate?->deposit + (float) $extra?->deposit;
            }
        });
    }

    /**
     * Confirming sends the contract to sign.
     */
    protected static function sendContractOnConfirm(): void
    {
        static::saved(function (RentalRequest $request): void {
            if ($request->status === RentalStatus::Confirmed && ($request->wasRecentlyCreated || $request->wasChanged('status'))) {
                app(AutoContract::class)->onConfirmed($request, (bool) settings()->get('rental_contract_auto_send'));
            }
        });
    }

    /**
     * HA-YYYY-NNN, numbered per year of submission.
     */
    public static function nextReference(): string
    {
        $prefix = 'HA-'.now()->format('Y').'-';

        $last = DB::table('rental_requests')
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->lockForUpdate()
            ->value('reference');

        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function rentalRate(): BelongsTo
    {
        return $this->belongsTo(RentalRate::class);
    }

    public function rentalExtra(): BelongsTo
    {
        return $this->belongsTo(RentalExtra::class);
    }

    public function drinks(): HasMany
    {
        return $this->hasMany(RentalRequestProduct::class);
    }

    public function drinkCounts(): HasMany
    {
        return $this->hasMany(RentalDrinkCount::class);
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

    /**
     * The invoicing customer, created from the renter's details the first time.
     */
    public function ensureCustomer(): Customer
    {
        if ($this->customer) {
            return $this->customer;
        }

        $customer = Customer::findOrCreateFrom([
            'name' => $this->organisation ?: $this->fullName(),
            'contact_name' => $this->organisation ? $this->fullName() : null,
            'vat_number' => $this->company_number,
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
        return ContractType::Rental;
    }

    public function contractSignerName(): string
    {
        return $this->fullName();
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
        $this->loadMissing('rentalRate', 'rentalExtra');
        $settings = settings();

        return array_map(fn ($value): string => (string) $value, [
            'referentie' => e($this->reference),
            'datum' => e(ucfirst($this->event_date->translatedFormat('l j F Y'))),
            'omschrijving' => nl2br(e(trim(($this->details ?: 'Event').($this->expected_guests ? " ({$this->expected_guests} personen verwacht)" : '')))),
            'huurder' => e($this->organisation ? "{$this->organisation} - {$this->fullName()}" : $this->fullName()),
            'huurder_naam' => e($this->fullName()),
            'huurder_adres' => e($this->address()),
            'huurder_contact' => e($this->email.' - '.$this->phone),
            'gebruikersgroep' => e($this->rentalRate?->name ?? '-'),
            'huurprijs' => e(euro($this->quoted_price)),
            'waarborg' => e(euro($this->quoted_deposit ?? $this->rentalRate?->deposit ?? 200)),
            'dj' => e($this->rentalExtra?->name ?? 'Geen'),
            'iban' => e((string) $settings->get('iban')),
            'prijzen' => view('contracts.partials.rental-prices')->render(),
            'verhuurder' => e((string) $settings->get('legal_name')),
            'vertegenwoordiger' => e((string) $settings->get('contract_representative')),
            'contact' => nl2br(e((string) $settings->get('contract_contact'))),
            'vandaag' => e(now()->format('d/m/Y')),
        ]);
    }

    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', RentalStatus::blocking());
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function address(): string
    {
        $line = trim("{$this->street} {$this->house_number}".($this->box ? " bus {$this->box}" : ''));

        return "{$line}, {$this->postal_code} {$this->city}";
    }
}
