<?php

namespace App\Contracts;

use App\Enums\ContractType;

/**
 * A model a contract can be drawn up for (a rental request or a cup loan).
 */
interface HasContract
{
    public function contractType(): ContractType;

    /**
     * @return array<string, string> placeholder (without braces) => value, HTML-safe
     */
    public function contractPlaceholders(): array;

    public function contractSignerName(): string;

    public function contractSignerEmail(): string;
}
