<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Only the month grid of the availability calendar, fetched by site.js when someone changes month.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // A plain 404 rather than a validation redirect: the caller is fetch(), which would follow it to a full page.
        $month = $request->query('maand');
        abort_unless(is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);

        return response()
            ->view('partials.calendar-grid', [
                'month' => CarbonImmutable::createFromFormat('!Y-m', $month),
                'withForm' => $request->boolean('form'),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
