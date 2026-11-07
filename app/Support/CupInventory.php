<?php

namespace App\Support;

use App\Models\CupLoan;
use Carbon\CarbonInterface;

/**
 * How many cups and crates are free in a period (confirmed and picked-up loans hold them).
 * Without a total in the settings there is no limit (null).
 */
class CupInventory
{
    /**
     * @return array{cups: int|null, crates: int|null}
     */
    public function available(CarbonInterface $from, CarbonInterface $to, ?int $ignoreLoanId = null): array
    {
        $loans = CupLoan::query()
            ->holdingCups()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($ignoreLoanId, fn ($query) => $query->whereKeyNot($ignoreLoanId))
            ->get(['cups', 'crates']);

        $cupsTotal = settings()->get('cups_total');
        $cratesTotal = settings()->get('crates_total');

        return [
            'cups' => filled($cupsTotal) ? max(0, (int) $cupsTotal - (int) $loans->sum('cups')) : null,
            'crates' => filled($cratesTotal) ? max(0, (int) $cratesTotal - (int) $loans->sum('crates')) : null,
        ];
    }

    /**
     * Why a loan does not fit, or null when it does.
     */
    public function problem(CarbonInterface $from, CarbonInterface $to, int $cups, int $crates, ?int $ignoreLoanId = null): ?string
    {
        $available = $this->available($from, $to, $ignoreLoanId);

        if ($available['cups'] !== null && $cups > $available['cups']) {
            return "Er zijn in deze periode nog maar {$available['cups']} bekers beschikbaar.";
        }

        if ($available['crates'] !== null && $crates > $available['crates']) {
            return "Er zijn in deze periode nog maar {$available['crates']} bakken beschikbaar.";
        }

        return null;
    }
}
