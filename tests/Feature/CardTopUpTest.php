<?php

namespace Tests\Feature;

use App\Enums\CardTransactionStatus;
use App\Enums\CardTransactionType;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\RelationManagers\CardTransactionsRelationManager;
use App\Mail\CardTopUpMail;
use App\Models\Member;
use App\Models\MemberCardTransaction;
use App\Models\User;
use App\Support\CardTopUps;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CardTopUpTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

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
        $this->member = Member::factory()->create(['card_number' => '04711', 'first_name' => 'An', 'last_name' => 'Van den Broeck', 'email' => 'an@example.com']);
    }

    private function stripeCheckout(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_top_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_top_1'])]);
    }

    public function test_a_card_is_topped_up_online_with_card_number_and_last_name(): void
    {
        $this->stripeCheckout();

        $this->post(route('card-topups.store'), ['card_number' => '04711', 'last_name' => ' van den broeck ', 'amount' => 20, 'provider' => 'stripe'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_top_1');

        $topUp = MemberCardTransaction::query()->sole();
        $this->assertSame(CardTransactionStatus::Pending, $topUp->status);
        $this->assertSame('20.00', $topUp->amount);
        $this->assertSame('an@example.com', $topUp->email);
        $this->assertSame(0.0, $this->member->cardBalance());
        Http::assertSent(fn (HttpRequest $request): bool => (int) ($request['line_items[0][price_data][unit_amount]'] ?? 0) === 2000
            && (string) ($request['metadata[card_topup_id]'] ?? '') === (string) $topUp->id);

        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_top_1' => Http::response(['id' => 'cs_top_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_top'])]);
        $this->get($topUp->publicUrl().'?betaald=1')->assertOk()->assertSee('Gelukt!');

        $this->assertTrue($topUp->fresh()->isCompleted());
        $this->assertSame(20.0, $this->member->cardBalance());
        Mail::assertSent(CardTopUpMail::class, fn (CardTopUpMail $mail): bool => $mail->hasTo('an@example.com'));

        $this->get($topUp->publicUrl())->assertOk();
        app(CardTopUps::class)->complete($topUp->fresh(), 'pi_top');
        Mail::assertSentCount(1);
        $this->assertSame(20.0, $this->member->cardBalance());
    }

    public function test_wrong_details_and_limits_are_refused(): void
    {
        $this->stripeCheckout();
        settings()->save(['topup' => [...(array) settings()->get('topup'), 'max_balance' => 50]]);

        $this->post(route('card-topups.store'), ['card_number' => '04711', 'last_name' => 'Peeters', 'amount' => 20])->assertSessionHasErrors('card_number');
        $this->post(route('card-topups.store'), ['card_number' => '4711', 'last_name' => 'Van den Broeck', 'amount' => 20])->assertSessionHasErrors('card_number');
        $this->post(route('card-topups.store'), ['card_number' => '04711', 'last_name' => 'Van den Broeck', 'amount' => 2])->assertSessionHasErrors('amount');

        app(CardTopUps::class)->book($this->member, 40, CardTransactionType::TopUpBar, null, null);
        $this->post(route('card-topups.store'), ['card_number' => '04711', 'last_name' => 'Van den Broeck', 'amount' => 20])
            ->assertSessionHasErrors(['amount' => 'Er mag maximaal €50 op een kaart staan. Je kan nog €10 opladen.']);

        $this->assertSame(1, MemberCardTransaction::query()->count());
        Http::assertNothingSent();
    }

    public function test_the_webhooks_complete_a_top_up(): void
    {
        $topUp = MemberCardTransaction::query()->create([
            'member_id' => $this->member->id, 'amount' => 10, 'type' => CardTransactionType::TopUpOnline, 'status' => CardTransactionStatus::Pending,
            'provider' => 'stripe', 'provider_reference' => 'cs_hook', 'token' => str_repeat('a', 40),
        ]);
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_hook' => Http::response(['id' => 'cs_hook', 'payment_status' => 'paid', 'payment_intent' => 'pi_h'])]);
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_hook', 'metadata' => ['card_topup_id' => $topUp->id]]]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $this->assertTrue($topUp->fresh()->isCompleted());

        $sumUp = MemberCardTransaction::query()->create([
            'member_id' => $this->member->id, 'amount' => 15, 'type' => CardTransactionType::TopUpOnline, 'status' => CardTransactionStatus::Pending,
            'provider' => 'sumup', 'provider_reference' => 'sumup-top', 'token' => str_repeat('b', 40),
        ]);
        Http::fake(['api.sumup.com/v0.1/checkouts/sumup-top' => Http::response(['id' => 'sumup-top', 'status' => 'PAID', 'transactions' => [['id' => 'txn-1', 'status' => 'SUCCESSFUL']]])]);
        $this->postJson(route('webhooks.sumup'), ['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => 'sumup-top'])->assertOk();

        $this->assertSame('txn-1', $sumUp->fresh()->provider_transaction);
        $this->assertSame(25.0, $this->member->cardBalance());
    }

    public function test_pos_purchases_count_and_the_member_page_shows_balance_and_history(): void
    {
        app(CardTopUps::class)->book($this->member, 30, CardTransactionType::TopUpBar, 'Opgeladen aan de toog', null);
        // The POS writes straight into the table with only the essentials.
        DB::table('member_card_transactions')->insert(['member_id' => $this->member->id, 'amount' => -4.5, 'description' => '2x Cola', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(25.5, $this->member->cardBalance());
        $this->assertSame(25.5, (float) DB::table('member_card_balances')->where('card_number', '04711')->value('balance'));

        $this->stripeCheckout();
        $this->get($this->member->selfServiceUrl())->assertOk()->assertSee('€25,50')->assertSee('2x Cola')->assertSee('Opladen en betalen');

        $this->post(route('members.topup', $this->member->token), ['amount' => 10, 'provider' => 'stripe'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_top_1');
    }

    public function test_online_top_up_is_unavailable_without_a_payment_provider(): void
    {
        app(Settings::class)->save(['payments' => Settings::defaults()['payments']]);

        $this->get(route('card-topups.create'))->assertOk()->assertSee('Online opladen is momenteel niet mogelijk');
        $this->post(route('card-topups.store'), ['card_number' => '04711', 'last_name' => 'Van den Broeck', 'amount' => 20])->assertNotFound();
    }

    public function test_unpaid_top_ups_are_given_up_after_a_day(): void
    {
        $this->travelTo(now()->subDays(2));
        $topUp = MemberCardTransaction::query()->create([
            'member_id' => $this->member->id, 'amount' => 10, 'type' => CardTransactionType::TopUpOnline, 'status' => CardTransactionStatus::Pending,
            'provider' => 'stripe', 'provider_reference' => 'cs_old', 'token' => str_repeat('c', 40),
        ]);
        $this->travelBack();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_old' => Http::response(['id' => 'cs_old', 'payment_status' => 'unpaid', 'status' => 'open'])]);

        $this->artisan('topups:expire')->assertSuccessful();

        $this->assertSame(CardTransactionStatus::Failed, $topUp->fresh()->status);
    }

    public function test_the_board_books_bar_top_ups_and_corrections(): void
    {
        $this->actingAs(User::factory()->withPermissions([Permissions::MEMBERS])->create());

        Livewire::test(CardTransactionsRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditMember::class])
            ->callTableAction('topUpBar', data: ['amount' => 20, 'description' => 'Cash'])
            ->callTableAction('correction', data: ['type' => 'refund', 'amount' => -5, 'description' => 'Terugbetaald'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(15.0, $this->member->cardBalance());
        $this->assertSame(2, $this->member->cardTransactions()->whereNotNull('created_by')->count());
    }
}
