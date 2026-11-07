<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who gets invoiced: renters, cup borrowers, or anyone added by hand.
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_name', 'vat_number', 'peppol_identifier', 'peppol_provider', 'peppol_checked_at', 'email', 'phone', 'street', 'house_number', 'box', 'postal_code', 'city', 'country', 'notes'];

    protected function casts(): array
    {
        return ['peppol_checked_at' => 'datetime'];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        $street = trim("{$this->street} {$this->house_number}".($this->box ? " bus {$this->box}" : ''));
        $city = trim("{$this->postal_code} {$this->city}");

        return array_values(array_filter([$street, $city, $this->country && $this->country !== 'BE' ? $this->country : null]));
    }

    /**
     * Finds the customer by e-mail (and name), or creates it: renters and cup borrowers become customers automatically.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function findOrCreateFrom(array $attributes): self
    {
        $existing = filled($attributes['email'] ?? null)
            ? static::query()->where('email', $attributes['email'])->where('name', $attributes['name'])->first()
            : null;

        return $existing ?? static::query()->create($attributes);
    }
}
