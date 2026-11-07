<?php

namespace Tests\Feature;

use App\Filament\Resources\BarSessions\Pages\ManageBarSessions;
use App\Models\BarSession;
use App\Models\User;
use App\Support\PosReportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class PosReportImportTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $overrides = []): string
    {
        return json_encode([
            'version' => 1,
            'bar_session' => [...[
                'date' => '2026-11-07',
                'label' => 'POS 2026-11-07',
                'opening_cash' => 100,
                'closing_cash' => 109.85,
                'card_total' => 6,
                'revenue' => 999, // ignored: the model calculates it
                'member_cards_sold' => 1,
                'notes' => 'Free: Birthday (1x)',
            ], ...$overrides],
        ]);
    }

    public function test_a_pos_report_becomes_a_bar_session_with_the_revenue_calculated_by_the_model(): void
    {
        [$session, $created] = app(PosReportImporter::class)->import($this->report(), null);

        $this->assertTrue($created);
        $this->assertSame('2026-11-07', $session->date->toDateString());
        $this->assertSame('15.85', $session->revenue); // 109.85 - 100 + 6, not the 999 in the file
        $this->assertSame(1, $session->member_cards_sold);
        $this->assertSame('Free: Birthday (1x)', $session->notes);
    }

    public function test_importing_the_same_report_twice_does_not_duplicate_it(): void
    {
        app(PosReportImporter::class)->import($this->report());
        [, $created] = app(PosReportImporter::class)->import($this->report());

        $this->assertFalse($created);
        $this->assertSame(1, BarSession::query()->count());
    }

    public function test_a_different_evening_is_a_new_session(): void
    {
        app(PosReportImporter::class)->import($this->report());
        app(PosReportImporter::class)->import($this->report(['date' => '2026-11-14', 'label' => 'POS 2026-11-14']));

        $this->assertSame(2, BarSession::query()->count());
    }

    public function test_garbage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PosReportImporter::class)->import('{"hello":"world"}');
    }

    public function test_the_import_action_is_on_the_bar_sessions_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageBarSessions::class)->assertActionExists('importPosReport');
    }
}
