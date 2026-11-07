<?php

namespace App\Support;

use App\Models\BarSession;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Imports the daily report (.json) that the POS (kassa) mails after closing the register.
 * The file holds a "bar_session" block that maps 1:1 onto the bar_sessions table; the revenue is
 * (as always) calculated by the BarSession model: closing cash - opening cash + card payments.
 */
class PosReportImporter
{
    /**
     * @return array{0: BarSession, 1: bool} the session and whether it was newly created (false = it was imported before)
     */
    public function import(string $json, ?int $userId = null): array
    {
        $data = json_decode($json, true);
        $row = is_array($data) && is_array($data['bar_session'] ?? null) ? $data['bar_session'] : null;

        $validator = Validator::make($row ?? [], [
            'date' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
            'opening_cash' => ['required', 'numeric'],
            'closing_cash' => ['required', 'numeric'],
            'card_total' => ['required', 'numeric'],
            'member_cards_sold' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($row === null || $validator->fails()) {
            throw new InvalidArgumentException('Dit is geen geldig dagrapport van de kassa.');
        }

        $values = $validator->validated();

        $existing = BarSession::query()
            ->whereDate('date', $values['date'])
            ->where('label', $values['label'] ?? null)
            ->where('opening_cash', $values['opening_cash'])
            ->where('closing_cash', $values['closing_cash'])
            ->where('card_total', $values['card_total'])
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        return [BarSession::query()->create([...$values, 'user_id' => $userId]), true];
    }
}
