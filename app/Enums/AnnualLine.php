<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The rows of the yearly overview, as in "Jaarrekening in progress.xlsx" (sheet "Overzicht Jaarrekening").
 * Expense lines are fed by the cost category of incoming invoices; income lines mostly by the app itself.
 */
enum AnnualLine: string implements HasLabel
{
    // 1.1 Uitgaven exploitatie beheer JOC & Jokot
    case Internet = 'internet';
    case RentUtilities = 'rent_utilities';
    case MaintenanceContracts = 'maintenance_contracts';
    case MaintenanceMaterials = 'maintenance_materials';
    case Insurance = 'insurance';
    case MembershipFees = 'membership_fees';
    case FirstAid = 'first_aid';
    case Administration = 'administration';
    // Werkingskosten Jokot
    case ActivitiesCost = 'activities_cost';
    case DrinksCost = 'drinks_cost';
    case MusicLight = 'music_light';
    case Pr = 'pr';
    case VolunteerFees = 'volunteer_fees';
    case EventsCost = 'events_cost';
    case OtherCost = 'other_cost';
    // 1.2 Ontvangsten exploitatie JOC
    case ActivitiesIncome = 'activities_income';
    case DrinksIncome = 'drinks_income';
    case MemberCards = 'member_cards';
    case EventsIncome = 'events_income';
    case RentalIncome = 'rental_income';
    case CupsIncome = 'cups_income';
    case OtherIncome = 'other_income';
    case Subsidy = 'subsidy';
    // 2. Investeringen
    case Investments = 'investments';

    public function getLabel(): string
    {
        return match ($this) {
            self::Internet => 'Internet',
            self::RentUtilities => 'Huur gebouw JOC + nutsvoorzieningen',
            self::MaintenanceContracts => 'Onderhoudscontracten',
            self::MaintenanceMaterials => 'Onderhoudsmaterialen',
            self::Insurance => 'Verzekeringen',
            self::MembershipFees => 'Lidgelden',
            self::FirstAid => 'EHBO',
            self::Administration => 'Administratie',
            self::ActivitiesCost, self::ActivitiesIncome => 'Activiteiten',
            self::DrinksCost, self::DrinksIncome => 'Drank (& snacks)',
            self::MusicLight => 'Muziek en licht',
            self::Pr => 'PR & Publiciteit',
            self::VolunteerFees => 'Vergoedingen vrijwilligers',
            self::EventsCost, self::EventsIncome => 'Evenementen',
            self::OtherCost => 'Overige uitgaven',
            self::MemberCards => 'Lidkaarten',
            self::RentalIncome => 'Verhuur aan derden',
            self::CupsIncome => 'Wisselbekers',
            self::OtherIncome => 'Overige ontvangsten',
            self::Subsidy => 'Subsidie gemeente',
            self::Investments => 'Investeringen',
        };
    }

    /**
     * The label with its section, for selects ("Uitgaven - Internet").
     */
    public function optionLabel(): string
    {
        $section = match (true) {
            $this === self::Investments => 'Investeringen',
            $this->isIncome() => 'Ontvangsten',
            default => 'Uitgaven',
        };

        return $section.' - '.$this->getLabel();
    }

    public function isIncome(): bool
    {
        return in_array($this, [...self::operatingIncome(), self::Subsidy], true);
    }

    /**
     * @return list<self>
     */
    public static function management(): array
    {
        return [self::Internet, self::RentUtilities, self::MaintenanceContracts, self::MaintenanceMaterials, self::Insurance, self::MembershipFees, self::FirstAid, self::Administration];
    }

    /**
     * @return list<self>
     */
    public static function operatingCosts(): array
    {
        return [self::ActivitiesCost, self::DrinksCost, self::MusicLight, self::Pr, self::VolunteerFees, self::EventsCost, self::OtherCost];
    }

    /**
     * @return list<self>
     */
    public static function operatingIncome(): array
    {
        return [self::ActivitiesIncome, self::DrinksIncome, self::MemberCards, self::EventsIncome, self::RentalIncome, self::CupsIncome, self::OtherIncome];
    }

    /**
     * Lines an incoming invoice can be booked on (via its cost category).
     *
     * @return array<string, string>
     */
    public static function expenseOptions(): array
    {
        return collect([...self::management(), ...self::operatingCosts(), self::Investments])
            ->mapWithKeys(fn (self $line): array => [$line->value => $line->optionLabel()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function allOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $line): array => [$line->value => $line->optionLabel()])->all();
    }
}
