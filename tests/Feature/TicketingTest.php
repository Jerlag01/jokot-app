<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Mail\OrderTicketsMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Settings;
use App\Support\Ticketing\TicketSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        app(Settings::class)->save(['payments' => [
            ...Settings::defaults()['payments'],
            'stripe_enabled' => true,
            'stripe_secret' => Crypt::encryptString('sk_test_123'),
            'stripe_webhook_secret' => Crypt::encryptString('whsec_test'),
            'sumup_enabled' => true,
            'sumup_api_key' => Crypt::encryptString('sup_sk_test'),
            'sumup_merchant_code' => 'MTEST',
        ]]);
    }

    private function type(array $attributes = [], array $event = []): TicketType
    {
        return TicketType::factory()->create([
            'event_id' => Event::factory()->create(['tickets_enabled' => true, 'title' => 'Jokot Fuif', ...$event])->id,
            ...$attributes,
        ]);
    }

    private function buyer(): array
    {
        return ['name' => 'Jan Janssens', 'email' => 'jan@example.com'];
    }

    public function test_the_event_page_shows_the_ticket_form(): void
    {
        $type = $this->type(['name' => 'Voorverkoop', 'price' => 8]);

        $this->get(route('events.show', $type->event))->assertOk()->assertSee('Voorverkoop')->assertSee('Bestellen en betalen')->assertSee('Stripe');
    }

    public function test_ordering_redirects_to_stripe_checkout(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
        $type = $this->type(['price' => 8]);

        $this->post(route('orders.store', $type->event), ['tickets' => [$type->id => 2], ...$this->buyer(), 'provider' => 'stripe', 'terms' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('16.00', $order->total);
        $this->assertSame('cs_test_1', $order->provider_reference);
        Http::assertSent(fn (HttpRequest $request): bool => (int) $request['line_items[0][price_data][unit_amount]'] === 800 && (int) $request['line_items[0][quantity]'] === 2);
    }

    public function test_the_return_page_confirms_a_paid_stripe_order_and_issues_tickets(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_paid' => Http::response(['id' => 'cs_paid', 'payment_status' => 'paid', 'status' => 'complete', 'payment_intent' => 'pi_1'])]);
        $type = $this->type(['price' => 8]);
        $order = app(TicketSales::class)->reserve($type->event, [$type->id => 3], $this->buyer(), PaymentProvider::Stripe);
        $order->update(['provider_reference' => 'cs_paid']);

        $this->get($order->publicUrl().'?betaald=1')->assertOk()->assertSee('Betaald, je tickets zijn klaar!');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('pi_1', $order->fresh()->provider_transaction);
        $this->assertSame(3, $order->tickets()->count());
        Mail::assertSent(OrderTicketsMail::class, fn (OrderTicketsMail $mail): bool => $mail->hasTo('jan@example.com'));
        $this->get(route('orders.tickets', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_stripe_webhook_needs_a_valid_signature(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_hook' => Http::response(['id' => 'cs_hook', 'payment_status' => 'paid', 'payment_intent' => 'pi_2'])]);
        $type = $this->type();
        $order = app(TicketSales::class)->reserve($type->event, [$type->id => 1], $this->buyer(), PaymentProvider::Stripe);
        $order->update(['provider_reference' => 'cs_hook']);
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_hook', 'metadata' => ['order_id' => $order->id]]]]);

        $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=forged', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->assertTrue($order->fresh()->isPending());

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');
        $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_sumup_checkout_and_webhook(): void
    {
        Http::fake([
            'api.sumup.com/v0.1/checkouts' => Http::response(['id' => 'sumup-1', 'status' => 'PENDING', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/sumup-1']),
            'api.sumup.com/v0.1/checkouts/sumup-1' => Http::response(['id' => 'sumup-1', 'status' => 'PAID', 'transactions' => [['id' => 'txn-9', 'status' => 'SUCCESSFUL']]]),
        ]);
        $type = $this->type(['price' => 5]);

        $this->post(route('orders.store', $type->event), ['tickets' => [$type->id => 1], ...$this->buyer(), 'provider' => 'sumup', 'terms' => '1'])
            ->assertRedirect('https://checkout.sumup.com/pay/sumup-1');

        $this->postJson(route('webhooks.sumup'), ['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => 'sumup-1'])->assertOk();

        $order = Order::query()->sole();
        $this->assertTrue($order->isPaid());
        $this->assertSame('txn-9', $order->provider_transaction);
    }

    public function test_free_tickets_are_issued_at_once(): void
    {
        $type = $this->type(['price' => 0]);

        $this->post(route('orders.store', $type->event), ['tickets' => [$type->id => 2], ...$this->buyer(), 'terms' => '1'])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(PaymentProvider::Free, $order->provider);
        $this->assertSame(2, $order->tickets()->count());
    }

    public function test_capacity_counts_paid_and_unexpired_pending_orders(): void
    {
        $type = $this->type(['capacity' => 5, 'price' => 8]);
        $sales = app(TicketSales::class);
        $sales->reserve($type->event, [$type->id => 3], $this->buyer(), PaymentProvider::Stripe);
        $expired = $sales->reserve($type->event, [$type->id => 2], $this->buyer(), PaymentProvider::Stripe);

        try {
            $sales->reserve($type->event, [$type->id => 1], $this->buyer(), PaymentProvider::Stripe);
            $this->fail('Sold out should be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('uitverkocht', $exception->getMessage());
        }

        $expired->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(2, $type->fresh()->remaining());
    }

    public function test_the_event_wide_capacity_and_sales_window(): void
    {
        $type = $this->type(['price' => 0], ['capacity' => 2]);
        $this->expectException(ValidationException::class);

        app(TicketSales::class)->reserve($type->event, [$type->id => 3], $this->buyer(), null);
    }

    public function test_ticket_types_outside_their_sales_window_are_refused(): void
    {
        $type = $this->type(['sales_start' => now()->addDay()]);

        $this->post(route('orders.store', $type->event), ['tickets' => [$type->id => 1], ...$this->buyer(), 'provider' => 'stripe', 'terms' => '1'])
            ->assertSessionHasErrors('tickets');
    }

    public function test_box_office_sales_are_paid_immediately(): void
    {
        $type = $this->type(['price' => 10]);

        $order = app(TicketSales::class)->boxOffice($type, 4, PaymentProvider::Cash, User::factory()->create());

        $this->assertTrue($order->isPaid());
        $this->assertSame('40.00', $order->total);
        $this->assertSame(4, $order->tickets()->count());
        Mail::assertNothingSent();
    }

    public function test_cancelling_a_paid_stripe_order_refunds_and_voids_the_tickets(): void
    {
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
        $type = $this->type();
        $order = app(TicketSales::class)->reserve($type->event, [$type->id => 2], $this->buyer(), PaymentProvider::Stripe);
        app(TicketSales::class)->markPaid($order, 'pi_9');

        app(TicketSales::class)->cancel($order->fresh(), refund: true);

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(0, $order->tickets()->valid()->count());
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/refunds') && $request['payment_intent'] === 'pi_9');
    }

    public function test_stale_pending_orders_expire_after_a_last_check(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_old', 'payment_status' => 'unpaid', 'status' => 'open'])]);
        $type = $this->type();
        $order = app(TicketSales::class)->reserve($type->event, [$type->id => 1], $this->buyer(), PaymentProvider::Stripe);
        $order->update(['provider_reference' => 'cs_old', 'expires_at' => now()->subMinutes(10)]);

        $this->assertSame(1, app(TicketSales::class)->expireStale());
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_marking_paid_twice_issues_tickets_once(): void
    {
        $type = $this->type();
        $order = app(TicketSales::class)->reserve($type->event, [$type->id => 2], $this->buyer(), PaymentProvider::Stripe);

        app(TicketSales::class)->markPaid($order);
        app(TicketSales::class)->markPaid($order->fresh());

        $this->assertSame(2, $order->tickets()->count());
    }
}
