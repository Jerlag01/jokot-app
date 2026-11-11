<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Support\Billit\Billit;
use App\Support\Billit\BillitClient;
use App\Support\Mailing;
use App\Support\Permissions;
use App\Support\RentalWeekdays;
use App\Support\Seo;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SETTINGS);
    }

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(SiteSettings $settings): void
    {
        $values = $settings->all();

        // Stored secrets are never sent back to the browser.
        foreach (SiteSettings::secretKeys() as $secret) {
            Arr::set($values, $secret, null);
        }

        $values['rental_blocked_weekdays'] = array_map('strval', (array) ($values['rental_blocked_weekdays'] ?? []));

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make('Algemeen')->icon(Heroicon::OutlinedHome)->schema([
                        Section::make('Jeugdhuis')
                            ->columns(2)
                            ->schema([
                                TextInput::make('site_name')->label('Naam')->required(),
                                TextInput::make('legal_name')->label('Juridische naam')->required(),
                                TextInput::make('address')->label('Adres')->required(),
                                TextInput::make('company_number')->label('Ondernemingsnummer'),
                                TextInput::make('email')->label('Algemeen e-mailadres')->email()->required(),
                                TextInput::make('bands_email')->label('E-mailadres voor bands')->email(),
                                TextInput::make('facebook_url')->label('Facebook')->url(),
                                TextInput::make('instagram_url')->label('Instagram')->url(),
                                TextInput::make('opening_hours_note')->label('Opmerking bij de openingsuren (**)')->columnSpanFull(),
                                TextInput::make('member_fee')->label('Lidgeld (per jaar)')->numeric()->prefix('€')->required(),
                                FileUpload::make('logo')->label('Logo in de footer')->image()->disk('public')->directory('settings')->visibility('public')->helperText('Leeg = het witte Jokot-logo.'),
                            ]),
                    ]),
                    Tab::make('Zoekmachines')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                        Section::make('Vindbaarheid')
                            ->description(fn (): string => Seo::indexable()
                                ? 'Deze site mag geïndexeerd worden. Sitemap voor Google Search Console en Bing Webmaster Tools: '.route('sitemap')
                                : 'Deze site wordt NIET geïndexeerd'.(app()->isProduction() ? ' (uitgeschakeld hieronder).' : ' (dit is geen productieomgeving: APP_ENV='.app()->environment().').'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('seo.indexable')->label('Zoekmachines mogen de site opnemen')->helperText('Werkt enkel op de productiesite; een testsite wordt nooit geïndexeerd.')->columnSpanFull(),
                                Textarea::make('seo.description')->label('Standaardbeschrijving')->helperText('Voor pagina\'s zonder eigen "Beschrijving voor Google". Ideaal 120-160 tekens.')->rows(3)->maxLength(300)->columnSpanFull(),
                                FileUpload::make('seo.share_image')->label('Deelafbeelding')->helperText('Getoond bij delen op Facebook, WhatsApp... Liefst 1200 × 630 pixels. Leeg = foto van de zaal.')->image()->disk('public')->directory('settings')->visibility('public')->columnSpanFull(),
                                TextInput::make('seo.google_verification')->label('Google Search Console-code')->helperText('Enkel de code uit content="..." van de HTML-tag.'),
                                TextInput::make('seo.bing_verification')->label('Bing Webmaster-code')->helperText('Enkel de code uit content="...".'),
                            ]),
                    ]),
                    Tab::make('Verhuur')->icon(Heroicon::OutlinedHomeModern)->schema([
                        Section::make('Huuraanvragen')
                            ->columns(2)
                            ->schema([
                                Toggle::make('rentals_open')->label('Huuraanvragen via de website toegelaten')->columnSpanFull(),
                                TextInput::make('rental_min_days_ahead')->label('Minstens ... dagen op voorhand')->numeric()->minValue(0)->required(),
                                TextInput::make('rental_max_months_ahead')->label('Maximaal ... maanden op voorhand')->numeric()->minValue(1)->required(),
                                TextInput::make('notification_email')->label('Nieuwe aanvragen en ondertekende contracten mailen naar')->email()->helperText('Leeg = enkel een melding in het beheer.'),
                                TextInput::make('corkage_fee')->label('Kurkgeld per fles')->numeric()->prefix('€')->required(),
                                CheckboxList::make('rental_blocked_weekdays')
                                    ->label('Weekdagen zonder aanvragen')
                                    ->helperText('Op deze dagen kan de zaal nooit aangevraagd worden via de website. Losse dagen en periodes blokkeer je bij Verhuur → Geblokkeerde data.')
                                    ->options(RentalWeekdays::options())
                                    ->columns(4)
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Contracten')
                            ->columns(2)
                            ->schema([
                                Toggle::make('rental_contract_auto_send')
                                    ->label('Huurcontract automatisch mailen bij bevestigen')
                                    ->helperText('Uit = het contract wordt klaargezet en je verstuurt het zelf via "Contract".'),
                                Toggle::make('cup_contract_auto_send')
                                    ->label('Wisselbekercontract automatisch mailen bij bevestigen'),
                                TextInput::make('contract_representative')->label('Vertegenwoordiger van het jeugdhuis')->helperText('Naam die in de contracten als ondertekenaar voor JOC Niel vzw staat.'),
                                Textarea::make('contract_contact')->label('Contactpersonen in het verhuurcontract')->rows(4),
                            ]),
                    ]),
                    Tab::make('Wisselbekers')->icon(Heroicon::OutlinedBeaker)->schema([
                        Section::make()
                            ->columns(3)
                            ->schema([
                                Toggle::make('cup_loans_open')->label('Aanvragen via de website toegelaten')->columnSpanFull(),
                                TextInput::make('cups_total')->label('Aantal bekers in bezit')->numeric()->minValue(0)->helperText('Leeg = geen controle op beschikbaarheid.'),
                                TextInput::make('crates_total')->label('Aantal bakken in bezit')->numeric()->minValue(0),
                                TextInput::make('cups_per_crate')->label('Bekers per bak')->numeric()->minValue(1)->helperText('Om het aantal bakken voor te stellen.'),
                                TextInput::make('cup_rental_fee')->label('Huurprijs (vast)')->numeric()->prefix('€')->required(),
                                TextInput::make('cup_fee')->label('Per missende/vuile beker')->numeric()->prefix('€')->required(),
                                TextInput::make('cup_crate_fee')->label('Per verloren/beschadigde bak')->numeric()->prefix('€')->required(),
                            ]),
                    ]),
                    Tab::make('Facturatie')->icon(Heroicon::OutlinedDocumentCurrencyEuro)->schema([
                        Section::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('iban')->label('IBAN')->required(),
                                TextInput::make('bic')->label('BIC'),
                                TextInput::make('vat_number')->label('Btw-nummer')->helperText('Leeg als de vzw niet btw-plichtig is.'),
                                Select::make('default_vat_rate')->label('Standaard btw-tarief')->options(['0' => '0%', '6' => '6%', '12' => '12%', '21' => '21%'])->required(),
                                TextInput::make('invoice_payment_days')->label('Betalingstermijn (dagen)')->numeric()->minValue(0)->required(),
                                TextInput::make('vat_note')->label('Btw-vermelding op de factuur')->placeholder('bv. Vrijgesteld van btw - art. 44 WBTW')->helperText('Vraag na bij je boekhouder welke vermelding van toepassing is.'),
                                Textarea::make('invoice_footer')->label('Extra tekst onderaan de factuur')->rows(2)->columnSpanFull(),
                            ]),
                    ]),
                    Tab::make('Tickets & betalingen')->icon(Heroicon::OutlinedTicket)->schema([
                        Section::make('Ticketverkoop')
                            ->columns(2)
                            ->schema([
                                Toggle::make('tickets_open')->label('Online ticketverkoop aan (algemene schakelaar)'),
                                TextInput::make('payments.order_minutes')->label('Tickets vasthouden tijdens betalen (minuten)')->numeric()->minValue(30)->maxValue(120)->required()->helperText('Minimum 30 (vereist door Stripe).'),
                            ]),
                        Section::make('Lidkaart opladen')
                            ->description(new HtmlString('Leden zetten online budget op hun lidkaart (via Stripe of SumUp hieronder) en betalen ermee aan de toog. Pagina: <a href="'.e(route('card-topups.create')).'" target="_blank" style="text-decoration:underline;">'.e(route('card-topups.create')).'</a>'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('topup.enabled')->label('Online opladen toelaten')->columnSpanFull(),
                                TextInput::make('topup.presets')->label('Voorgestelde bedragen')->helperText('Gescheiden door komma\'s, bv. 10,20,50.')->regex('/^\s*\d+(\s*,\s*\d+)*\s*$/'),
                                TextInput::make('topup.min')->label('Minimum per keer')->numeric()->prefix('€')->minValue(1)->required(),
                                TextInput::make('topup.max')->label('Maximum per keer')->numeric()->prefix('€')->minValue(1)->required(),
                                TextInput::make('topup.max_balance')->label('Maximaal saldo op een kaart')->numeric()->prefix('€')->helperText('Leeg = geen maximum.'),
                            ]),
                        Section::make('Stripe')
                            ->description(new HtmlString('Hosted checkout (Bancontact, kaarten, ...). Maak in het Stripe-dashboard een webhook naar <code>'.e(route('webhooks.stripe')).'</code> met de events <code>checkout.session.completed</code> en <code>checkout.session.async_payment_succeeded</code>.'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('payments.stripe_enabled')->label('Stripe gebruiken')->columnSpanFull(),
                                TextInput::make('payments.stripe_secret')->label('Secret key (sk_...)')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('payments.stripe_secret')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Wordt versleuteld opgeslagen.'),
                                TextInput::make('payments.stripe_webhook_secret')->label('Webhook signing secret (whsec_...)')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('payments.stripe_webhook_secret')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Wordt versleuteld opgeslagen.'),
                            ]),
                        Section::make('SumUp')
                            ->description('Hosted checkout van SumUp. De status wordt altijd bij SumUp nagevraagd; de webhook wordt per betaling automatisch ingesteld.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('payments.sumup_enabled')->label('SumUp gebruiken')->columnSpanFull(),
                                TextInput::make('payments.sumup_api_key')->label('API key (sup_sk_...)')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('payments.sumup_api_key')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Wordt versleuteld opgeslagen.'),
                                TextInput::make('payments.sumup_merchant_code')->label('Merchant code'),
                            ]),
                    ]),
                    Tab::make('Billit & Doccle')->icon(Heroicon::OutlinedCloud)->schema([
                        Section::make('Billit')
                            ->description(new HtmlString('Facturen worden in Billit aangemaakt (met onze PDF en mededeling) en door Billit afgeleverd via Peppol, e-mail of brief. Maak een API-sleutel in Billit (Profiel &rarr; API) en test eerst in de <a href="https://my.sandbox.billit.be" target="_blank" style="text-decoration:underline;">sandbox</a>. Gebruik daarna de knoppen bovenaan: "Billit testen" en "Billit-webhook registreren".'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('billit.enabled')->label('Billit gebruiken')->columnSpanFull(),
                                Select::make('billit.environment')->label('Omgeving')->options(['sandbox' => 'Sandbox (test)', 'production' => 'Productie'])->required(),
                                TextInput::make('billit.party_id')->label('PartyID (bedrijf)')->numeric()->helperText('Zie "Billit testen". Leeg als je account maar één bedrijf heeft.'),
                                TextInput::make('billit.api_key')->label('API-sleutel')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('billit.api_key')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Wordt versleuteld opgeslagen.'),
                                Select::make('billit.transport')->label('Standaard afleveren via')->options(Billit::TRANSPORTS)->required(),
                                Toggle::make('billit.push_payments')->label('Betalingen die we hier registreren doorgeven aan Billit'),
                                Toggle::make('billit.import_incoming')->label('Inkomende facturen uit Billit automatisch inboeken'),
                                Text::make(fn (): string => filled(settings()->get('billit.webhook_id'))
                                    ? 'Webhook actief (#'.settings()->get('billit.webhook_id').') naar '.route('webhooks.billit')
                                    : 'Nog geen webhook: status en betalingen worden elk uur opgehaald (vereist de cron).')->columnSpanFull(),
                            ]),
                        Section::make('Doccle')
                            ->description(new HtmlString('Doccle-gebruikers die hun Peppol-adres via Doccle registreerden, ontvangen onze Peppol-facturen (via Billit) gewoon in Doccle. Bij "Peppol" op een klant zie je wie hun ontvanger is. Rechtstreeks naar Doccle versturen (op e-mailadres of klantnummer) vraagt een contract met Doccle: hun verzend-API is niet publiek. Neem contact op via <a href="https://doccle.be/en-be/for-companies/contact-doccle-sales-team/" target="_blank" style="text-decoration:underline;">doccle.be</a> en bezorg ons de API-documentatie, dan koppelen we die ook.')),
                    ]),
                    Tab::make('E-mail')->icon(Heroicon::OutlinedEnvelope)->schema([
                        $this->mailSection(),
                    ]),
                    Tab::make('Beveiliging')->icon(Heroicon::OutlinedShieldCheck)->schema([
                        Section::make('Automatisch blokkeren')
                            ->description('Elke aanmelding (beheer en check-in-app) wordt bijgehouden bij Beveiliging → Aanmeldpogingen, met het land van het IP-adres.')
                            ->columns(3)
                            ->schema([
                                Toggle::make('security.auto_block')->label('IP-adres automatisch blokkeren na te veel mislukte aanmeldingen')->columnSpanFull()->live(),
                                TextInput::make('security.max_attempts')->label('Na ... mislukte pogingen')->numeric()->integer()->minValue(1)->required()->visible(fn (Get $get): bool => (bool) $get('security.auto_block')),
                                TextInput::make('security.window_minutes')->label('Binnen ... minuten')->numeric()->integer()->minValue(1)->required()->visible(fn (Get $get): bool => (bool) $get('security.auto_block')),
                                TextInput::make('security.block_minutes')->label('Blokkeren voor ... minuten')->helperText('0 = permanent')->numeric()->integer()->minValue(0)->required()->visible(fn (Get $get): bool => (bool) $get('security.auto_block')),
                                Textarea::make('security.whitelist')
                                    ->label('Nooit blokkeren (whitelist)')
                                    ->helperText(fn (): string => 'IP-adressen of reeksen (CIDR), één per lijn. Je huidige IP-adres is '.request()->ip().'.')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TextInput::make('security.retention_days')->label('Aanmeldpogingen bewaren (dagen)')->numeric()->integer()->minValue(30)->required(),
                            ]),
                        Section::make('Bezoekersstatistieken')
                            ->description('Geen cookies en geen IP-adressen: een bezoeker is een anonieme code die elke dag verandert. Daarom is geen cookiebanner nodig.')
                            ->columns(3)
                            ->schema([
                                Toggle::make('stats.enabled')->label('Bezoeken tellen'),
                                Toggle::make('stats.exclude_logged_in')->label('Aangemelde beheerders niet meetellen'),
                                TextInput::make('stats.retention_days')->label('Bewaren (dagen)')->numeric()->integer()->minValue(30)->required(),
                            ]),
                    ]),
                ]),
            ]);
    }

    private function mailSection(): Section
    {
        $usesSmtp = fn (Get $get): bool => $get('mail.mailer') === 'smtp';

        return Section::make('E-mail')
            ->description(new HtmlString('Hoe de website mails verstuurt (bevestigingen van huuraanvragen, wachtwoord vergeten, ...). Gebruik de knop "Testmail versturen" bovenaan na het opslaan. De teksten van de mails pas je aan bij <a href="'.MailTemplateResource::getUrl().'" style="text-decoration:underline;">Systeem → E-mailsjablonen</a>.'))
            ->columns(2)
            ->schema([
                Toggle::make('mail.enabled')
                    ->label('E-mails versturen')
                    ->helperText('Uit = de website verstuurt geen enkele mail meer (ook geen facturen, contracten of wachtwoord-vergeten). Ze worden enkel in het logbestand genoteerd. De testmail werkt wel.')
                    ->live()
                    ->columnSpanFull(),
                Fieldset::make('Automatische mails')
                    ->visible(fn (Get $get): bool => (bool) $get('mail.enabled'))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(collect(Mailing::automaticTypes())
                        ->map(fn (string $label, string $type) => Toggle::make('mail.auto_'.$type)->label($label))
                        ->values()
                        ->all()),
                Select::make('mail.mailer')
                    ->label('Verzendmethode')
                    ->options([
                        'smtp' => 'SMTP',
                        'sendmail' => 'Sendmail (server)',
                        'log' => 'Niet versturen, enkel loggen',
                    ])
                    ->placeholder('Standaard (.env)')
                    ->live(),
                TextInput::make('mail.host')
                    ->label('SMTP-server')
                    ->placeholder('smtp.example.com')
                    ->required($usesSmtp)
                    ->visible($usesSmtp),
                TextInput::make('mail.port')
                    ->label('Poort')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->required($usesSmtp)
                    ->visible($usesSmtp),
                Select::make('mail.scheme')
                    ->label('Beveiliging')
                    ->options([
                        'smtp' => 'STARTTLS (meestal poort 587)',
                        'smtps' => 'SSL/TLS (meestal poort 465)',
                    ])
                    ->placeholder('Automatisch')
                    ->visible($usesSmtp),
                TextInput::make('mail.username')
                    ->label('Gebruikersnaam')
                    ->autocomplete('off')
                    ->visible($usesSmtp),
                TextInput::make('mail.password')
                    ->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->helperText(fn (): string => filled(settings()->get('mail.password'))
                        ? 'Er is een wachtwoord opgeslagen. Leeg laten om het te behouden.'
                        : 'Wordt versleuteld opgeslagen.')
                    ->visible($usesSmtp),
                TextInput::make('mail.from_address')
                    ->label('Afzender (e-mailadres)')
                    ->email()
                    ->placeholder(fn (): string => (string) settings()->get('email')),
                TextInput::make('mail.from_name')
                    ->label('Afzender (naam)')
                    ->placeholder(fn (): string => settings()->siteName()),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Opslaan')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(SiteSettings $settings): void
    {
        $data = $this->form->getState();
        $oldLogo = $settings->get('logo');

        if (filled($oldLogo) && $oldLogo !== ($data['logo'] ?? null)) {
            Storage::disk('public')->delete($oldLogo);
        }

        // Nested groups keep the keys hidden fields did not send.
        foreach (['mail', 'payments', 'billit', 'security', 'stats', 'seo', 'topup'] as $group) {
            $data[$group] = [...(array) $settings->get($group), ...($data[$group] ?? [])];
        }

        $data['mail']['port'] = filled($data['mail']['port'] ?? null) ? (int) $data['mail']['port'] : null;
        $data['payments']['order_minutes'] = max(30, (int) ($data['payments']['order_minutes'] ?? 30));

        foreach (SiteSettings::secretKeys() as $secret) {
            $value = Arr::get($data, $secret);
            Arr::set($data, $secret, filled($value) ? Crypt::encryptString($value) : $settings->get($secret));
        }

        foreach (['cups_total', 'crates_total', 'cups_per_crate'] as $optionalNumber) {
            $data[$optionalNumber] = filled($data[$optionalNumber] ?? null) ? (int) $data[$optionalNumber] : null;
        }

        $data['rental_min_days_ahead'] = (int) $data['rental_min_days_ahead'];
        $data['rental_blocked_weekdays'] = array_values(array_map('intval', $data['rental_blocked_weekdays'] ?? []));
        sort($data['rental_blocked_weekdays']);

        foreach (['max_attempts', 'window_minutes', 'block_minutes', 'retention_days'] as $number) {
            $data['security'][$number] = (int) ($data['security'][$number] ?? 0);
        }

        $data['stats']['retention_days'] = (int) ($data['stats']['retention_days'] ?? 730);
        $data['rental_max_months_ahead'] = (int) $data['rental_max_months_ahead'];

        $settings->save($data);

        foreach (SiteSettings::secretKeys() as $secret) {
            Arr::set($this->data, $secret, null);
        }

        Notification::make()->success()->title('Instellingen opgeslagen')->send();
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('billitTest')
                ->label('Billit testen')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->visible(fn (): bool => filled(settings()->get('billit.api_key')))
                ->action(function (): void {
                    try {
                        $info = app(BillitClient::class)->accountInformation();
                        $companies = collect($info['Companies'] ?? [])->map(fn (array $company): string => ($company['Name'] ?? '?').' (PartyID '.($company['PartyID'] ?? '?').')')->implode(', ');
                        Notification::make()->success()->persistent()->title('Verbonden met Billit ('.settings()->get('billit.environment').')')->body($companies ? 'Bedrijven: '.$companies : null)->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->persistent()->title('Billit-verbinding mislukt')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('billitWebhook')
                ->label('Billit-webhook registreren')
                ->icon(Heroicon::OutlinedBolt)
                ->color('gray')
                ->visible(fn (): bool => filled(settings()->get('billit.api_key')))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Billit meldt wijzigingen aan facturen (aflevering, betaling, nieuwe inkomende facturen) meteen aan '.route('webhooks.billit').'. Een eerder geregistreerde webhook wordt vervangen.')
                ->action(function (SiteSettings $settings): void {
                    $client = app(BillitClient::class);

                    try {
                        if (filled($old = $settings->get('billit.webhook_id'))) {
                            $client->deleteWebhook((int) $old);
                        }

                        $webhook = $client->createWebhook(route('webhooks.billit'));
                        $settings->save(['billit' => [
                            ...(array) $settings->get('billit'),
                            'webhook_id' => $webhook['WebhookID'] ?? null,
                            'webhook_secret' => filled($webhook['Secret'] ?? null) ? Crypt::encryptString($webhook['Secret']) : null,
                        ]]);
                        Notification::make()->success()->title('Webhook geregistreerd')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->persistent()->title('Webhook registreren mislukt')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('sendTestMail')
                ->label('Testmail versturen')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalDescription('Verstuurt een testmail met de opgeslagen instellingen. Sla wijzigingen dus eerst op.')
                ->schema([
                    TextInput::make('recipient')
                        ->label('Naar')
                        ->email()
                        ->required()
                        ->default(fn (): ?string => auth()->user()?->email),
                ])
                ->modalSubmitActionLabel('Versturen')
                ->action(function (array $data, SiteSettings $settings): void {
                    // The mailer may already be built with older settings during this request.
                    Mail::purge();
                    $settings->applyMailConfig();

                    try {
                        Mail::raw(
                            'Dit is een testmail van de website van '.$settings->siteName().'. De mailinstellingen werken.',
                            fn ($message) => $message->to($data['recipient'])->subject($settings->siteName().' - testmail')
                                ->getSymfonyMessage()->getHeaders()->addTextHeader(Mailing::TEST_HEADER, '1'),
                        );

                        Notification::make()->success()->title('Testmail verstuurd naar '.$data['recipient'])
                            ->body(collect([
                                config('mail.default') === 'log' ? 'Let op: de verzendmethode is "loggen", de mail staat enkel in storage/logs/laravel.log.' : null,
                                Mailing::enabled() ? null : 'E-mails versturen staat uit: andere mails worden niet verstuurd.',
                            ])->filter()->implode(' ') ?: null)
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->persistent()
                            ->title('Testmail mislukt')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }
}
