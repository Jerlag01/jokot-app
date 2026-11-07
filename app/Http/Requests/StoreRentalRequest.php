<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalRequest extends FormRequest
{
    /**
     * The form is public; spam is handled by the honeypot field and the route throttle.
     */
    public function authorize(): bool
    {
        return (bool) settings()->get('rentals_open', true);
    }

    /**
     * Back to the form itself, not the top of the page.
     */
    protected function getRedirectUrl(): string
    {
        return strtok(parent::getRedirectUrl(), '#').'#huuraanvraag';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [$earliest, $latest] = app(Availability::class)->bookingWindow();

        return [
            'event_date' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.$earliest->toDateString(),
                'before_or_equal:'.$latest->toDateString(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $value);

                    if (! $date) {
                        return;
                    }

                    $availability = app(Availability::class);

                    if (! $availability->isAvailable($date)) {
                        $fail('Deze datum is helaas niet meer beschikbaar. Kies een andere datum in de kalender.');
                    } elseif ($availability->blockedBetween($date, $date) !== []) {
                        $fail('Op deze datum kan je de zaal niet huren. Kies een andere datum in de kalender.');
                    }
                },
            ],
            'rental_rate_id' => ['required', Rule::exists('rental_rates', 'id')->where('is_active', true)],
            'rental_extra_id' => ['nullable', Rule::exists('rental_extras', 'id')->where('is_active', true)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'organisation' => ['nullable', 'string', 'max:150'],
            'company_number' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:150'],
            'house_number' => ['required', 'string', 'max:20'],
            'box' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:5000'],
            'expected_guests' => ['nullable', 'integer', 'min:1', 'max:350'],
            'brings_own_wine' => ['required', 'boolean'],
            'drinks' => ['nullable', 'array'],
            'drinks.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        [$earliest, $latest] = app(Availability::class)->bookingWindow();

        return [
            'event_date.after_or_equal' => 'Reserveren kan ten vroegste '.$earliest->translatedFormat('j F Y').' (minstens '.settings()->get('rental_min_days_ahead').' dagen op voorhand).',
            'event_date.before_or_equal' => 'Reserveren kan maximaal '.settings()->get('rental_max_months_ahead').' maanden op voorhand (tot '.$latest->translatedFormat('j F Y').').',
            'terms.accepted' => 'Je moet akkoord gaan met de voorwaarden.',
            'expected_guests.max' => 'De zaal is geschikt voor maximaal 350 personen.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_date' => 'datum',
            'rental_rate_id' => 'gebruikersgroep',
            'rental_extra_id' => 'DJ-tafel',
            'first_name' => 'voornaam',
            'last_name' => 'achternaam',
            'organisation' => 'organisatie',
            'company_number' => 'ondernemingsnummer',
            'email' => 'e-mail',
            'phone' => 'telefoon',
            'street' => 'straat',
            'house_number' => 'huisnummer',
            'box' => 'busnummer',
            'postal_code' => 'postcode',
            'city' => 'gemeente',
            'expected_guests' => 'verwacht aantal',
            'brings_own_wine' => 'eigen wijn, cava of champagne',
        ];
    }

    /**
     * Product lines with a quantity above zero, for orderable drinks only.
     *
     * @return list<array{product_id: int, name: string, quantity: int}>
     */
    public function drinkLines(): array
    {
        $quantities = collect($this->validated('drinks') ?? [])->map(fn ($quantity): int => (int) $quantity)->filter();

        if ($quantities->isEmpty()) {
            return [];
        }

        return Product::query()->orderable()->whereKey($quantities->keys())->get()
            ->map(fn (Product $drink): array => ['product_id' => $drink->id, 'name' => $drink->label(), 'quantity' => $quantities[$drink->id]])
            ->values()
            ->all();
    }
}
