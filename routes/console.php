<?php

use App\Enums\InvoiceStatus;
use App\Models\BlockedIp;
use App\Models\Invoice;
use App\Models\LoginAttempt;
use App\Support\Billit\Billit;
use App\Support\Security\GeoIp;
use App\Support\Security\IpBlocker;
use App\Support\Stats\VisitorStats;
use App\Support\Ticketing\TicketSales;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tickets:expire-orders', function (TicketSales $sales): void {
    $this->info($sales->expireStale().' onbetaalde bestellingen vrijgegeven.');
})->purpose('Release tickets held by unpaid orders (checked with the payment provider first)');

// Needs the server cron: * * * * * php8.4 /usr/share/nginx/html/devops/jokot-app/artisan schedule:run
Schedule::command('tickets:expire-orders')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('billit:sync', function (Billit $billit): void {
    if (! $billit->client()->isConfigured()) {
        $this->warn('Billit is niet ingesteld.');

        return;
    }

    // Delivery and payment status of invoices sent in the last 90 days that are not settled yet.
    $synced = 0;
    Invoice::query()
        ->whereNotNull('billit_order_id')
        ->where('billit_sent_at', '>=', now()->subDays(90))
        ->where(fn ($query) => $query->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])->orWhere('billit_delivery_status', 'Pending'))
        ->each(function (Invoice $invoice) use ($billit, &$synced): void {
            try {
                $billit->sync($invoice);
                $synced++;
            } catch (Throwable $exception) {
                report($exception);
            }
        });

    $imported = settings()->get('billit.import_incoming') ? $billit->importIncoming() : 0;
    $this->info("{$synced} facturen bijgewerkt, {$imported} inkomende facturen geïmporteerd.");
})->purpose('Sync delivery/payment status with Billit and import incoming invoices');

Schedule::command('billit:sync')->hourly()->withoutOverlapping();

Artisan::command('geoip:update', function (GeoIp $geo): void {
    $this->info('Gedownload: '.$geo->update());
})->purpose('Download the free DB-IP country database used for login and visitor countries');

Schedule::command('geoip:update')->monthlyOn(3, '04:20');

Artisan::command('security:unblock {ip?} {--all : Remove every block}', function (): int {
    $query = BlockedIp::query();

    if (! $this->option('all')) {
        if (! $this->argument('ip')) {
            $this->error('Geef een IP-adres of gebruik --all.');

            return 1;
        }

        $query->where('ip', $this->argument('ip'));
    }

    $count = 0;
    $query->get()->each(function (BlockedIp $block) use (&$count): void {
        $block->delete();
        $count++;
    });

    IpBlocker::flush();
    $this->info("{$count} blokkering(en) verwijderd.");

    return 0;
})->purpose('Remove an IP block (when you locked yourself out of the admin site)');

Artisan::command('logs:prune', function (VisitorStats $stats): void {
    $logins = LoginAttempt::query()->where('created_at', '<', now()->subDays(max(30, (int) settings()->get('security.retention_days', 365))))->delete();
    $expired = BlockedIp::query()->where('is_automatic', true)->whereNotNull('blocked_until')->where('blocked_until', '<', now()->subDays(30))->delete();
    $views = $stats->prune((int) settings()->get('stats.retention_days', 730));
    IpBlocker::flush();
    $this->info("{$logins} aanmeldpogingen, {$expired} verlopen blokkeringen en {$views} paginaweergaven opgeruimd.");
})->purpose('Remove old login attempts, expired automatic blocks and old page views');

Schedule::command('logs:prune')->dailyAt('03:40');
