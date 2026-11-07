<?php

namespace Database\Seeders;

use App\Enums\AnnualLine;
use App\Enums\ContractType;
use App\Enums\ProductCategory;
use App\Models\ContractTemplate;
use App\Models\ExpenseCategory;
use App\Models\Page;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Roles, contract templates (from "gebruiksovereenkomst leeg DEF.docx" and "Overeenkomst-Uitlenen-wisselbekers Jh Jokot.docx"),
 * product categories and renter prices, and the cup loan page. Safe to re-run: never overwrites edits.
 */
class JokotModulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->roles();
        $this->products();
        $this->contractTemplates();
        $this->pages();
        $this->expenseCategories();
    }

    private function expenseCategories(): void
    {
        // Category => line in the yearly accounts (as the "keuzemenu" of the Excel workbook).
        $categories = [
            'Drank' => AnnualLine::DrinksCost,
            'Snacks' => AnnualLine::DrinksCost,
            'Internet' => AnnualLine::Internet,
            'Huur' => AnnualLine::RentUtilities,
            'Energie & water' => AnnualLine::RentUtilities,
            'Onderhoudscontracten' => AnnualLine::MaintenanceContracts,
            'Onderhoud & herstellingen' => AnnualLine::MaintenanceMaterials,
            'Materiaal & inrichting' => AnnualLine::MaintenanceMaterials,
            'Verzekeringen' => AnnualLine::Insurance,
            'Lidgeld koepel (Formaat)' => AnnualLine::MembershipFees,
            'EHBO' => AnnualLine::FirstAid,
            'Bank & administratie' => AnnualLine::Administration,
            'Gemeente & vergunningen' => AnnualLine::Administration,
            'Activiteiten' => AnnualLine::ActivitiesCost,
            'Artiesten & DJ\'s' => AnnualLine::MusicLight,
            'Auteursrechten (Unisono)' => AnnualLine::MusicLight,
            'Promotie' => AnnualLine::Pr,
            'Socials & website' => AnnualLine::Pr,
            'Vrijwilligersvergoedingen' => AnnualLine::VolunteerFees,
            'Evenementen' => AnnualLine::EventsCost,
            'Investeringen' => AnnualLine::Investments,
            'Andere' => AnnualLine::OtherCost,
        ];

        $sort = 0;
        foreach ($categories as $name => $line) {
            $category = ExpenseCategory::query()->firstOrCreate(['name' => $name], ['sort' => $sort, 'annual_line' => $line]);

            if ($category->annual_line === null) {
                $category->update(['annual_line' => $line]);
            }

            $sort++;
        }

        // The earlier general "Activiteiten" category was used for events too; keep it, just link it.
        ExpenseCategory::query()->where('name', 'Activiteiten')->whereNull('annual_line')->update(['annual_line' => AnnualLine::ActivitiesCost->value]);
    }

    private function roles(): void
    {
        foreach (Permissions::defaultRoles() as $name => $role) {
            Role::query()->firstOrCreate(['name' => $name], $role);
        }

        // Nobody may be locked out: users without a role (created before roles existed) become admin.
        $admin = Role::query()->where('is_admin', true)->orderBy('id')->first();
        User::query()->whereDoesntHave('roles')->each(fn (User $user) => $user->roles()->attach($admin));
    }

    private function products(): void
    {
        $crate = 24;
        // name => [category, units per package, bar price, renter price] (prices from the rental contract)
        $products = [
            'Cola 20cl' => [ProductCategory::Soft, $crate, 1.50, 1.50],
            'Cola Zero 20cl' => [ProductCategory::Soft, $crate, 1.50, 1.50],
            'Fanta 20cl' => [ProductCategory::Soft, $crate, 1.50, 1.50],
            'Ice Tea 20cl' => [ProductCategory::Soft, $crate, 1.50, 1.50],
            'Ice Tea Green 20cl' => [ProductCategory::Soft, $crate, 1.50, 1.50],
            'Plat water 20cl' => [ProductCategory::Soft, $crate, 1.00, 1.00],
            'Bruiswater 20cl' => [ProductCategory::Soft, $crate, 1.00, 1.00],
            'Pils 25cl' => [ProductCategory::Beer, $crate, 1.80, 1.80],
            'Vat 20L' => [ProductCategory::Beer, null, null, 120],
            'Vat 50L' => [ProductCategory::Beer, null, null, 220],
            'Kriek 33cl' => [ProductCategory::Beer, $crate, 2.50, 2.50],
            'Duvel' => [ProductCategory::Beer, $crate, 3.00, 3.00],
            'La Chouffe Blond' => [ProductCategory::Beer, $crate, 3.00, 3.00],
            'Vedett' => [ProductCategory::Beer, $crate, 3.00, 3.00],
            'Carlsberg 0%' => [ProductCategory::Beer, $crate, 1.80, 1.80],
            'Cava Mas D’Ancosa' => [ProductCategory::Wine, null, null, null],
        ];

        foreach ($products as $name => [$category, $perPackage, $barPrice, $rentalPrice]) {
            $product = Product::query()->where('name', $name)->first();

            if ($product && $product->rental_price === null && $product->units_per_package === null) {
                $product->update(['category' => $category, 'units_per_package' => $perPackage, 'bar_price' => $product->bar_price ?? $barPrice, 'rental_price' => $rentalPrice]);
            }
        }

        Product::query()->firstOrCreate(['name' => 'Grote fles frisdrank/water 1L'], [
            'category' => ProductCategory::Soft, 'rental_price' => 5, 'is_orderable' => false, 'sort' => 20,
        ]);
    }

    private function contractTemplates(): void
    {
        ContractTemplate::query()->firstOrCreate(['type' => ContractType::Rental], [
            'title' => 'Gebruiksovereenkomst - voorzieningen en reglement van inwendige orde bij gebruik',
            'body' => <<<'HTML'
<table>
<tr><th style="width: 30%;">Omschrijving</th><td>{omschrijving}<br>Datum: <strong>{datum}</strong></td></tr>
<tr><th>Huurder</th><td>{huurder}<br>{huurder_adres}<br>{huurder_contact}</td></tr>
<tr><th>Gebruikersgroep</th><td>{gebruikersgroep} - huurprijs {huurprijs}, waarborg {waarborg}</td></tr>
<tr><th>DJ-installatie</th><td>{dj}</td></tr>
</table>
<h2>Adres</h2>
<p>Landbouwstraat 145<br>2845 Niel (Antwerpen)<br>België</p>
<h2>Zaal</h2>
<p>Polyvalente zaal, deze kan worden opgedeeld in 2 kleinere zalen. Hiernaast kunnen jullie de afgesloten koer buiten ook gebruiken.</p>
<p>1 toog met 6 tapkranen en 2 grote frigo’s bestaande uit 3 kleinere compartimenten, 7 staantafels en 18 barkrukken.</p>
<h2>Muziek</h2>
<p>Entertainment is zelf te voorzien.</p>
<p>Er is geen draaitafel voor een DJ aanwezig in het JOC maar het is mogelijk om dit aan een standaardprijs te huren door ons. Zie hieronder voor meer info in betrekking tot de prijs.</p>
<h2>Prijzen</h2>
{prijzen}
<h2>Waarborg</h2>
<p>Voor het gebruik van de zaal vragen wij een voorschot/waarborg van {waarborg}. Dit bedrag moet ten laatste 3 weken op voorhand gestort worden op volgend rekeningnummer: <strong>{iban}</strong> met vermelding <strong>Waarborg huur {huurder_naam} ({referentie})</strong>. Achteraf wordt dit bedrag in mindering gebracht bij de factuur van de drank. Moest er op de factuur niet aan de waarborg gekomen worden, zullen wij de overschot terugstorten op het door jullie doorgegeven rekeningnummer.</p>
<p>Dit voorschot zullen we gebruiken om een deel van de drank voor te schieten en voor mogelijke schade te dekken aan de infrastructuur van het gebouw en/of materiële schade aan apparatuur van het jeugdhuis.</p>
<h2>Facturatie</h2>
<p>Voor en na het evenement zullen wij als jeugdhuis de drank komen tellen. Zodat wij weten wat er de avond van het evenement verbruikt is, wij vragen ook dat de huurder zelf een telling doet zodat er een dubbele controle is.</p>
<p>Wij zullen de factuur zo snel mogelijk na de telling aan de huurder bezorgen. Vanaf ontvangst van de factuur verwachten wij dat deze betaald wordt binnen de 30 kalenderdagen.</p>
<h2>Reglement</h2>
<p>JOC Niel vzw kan niet verantwoordelijk worden gesteld voor opgelopen schade. Al de schade die voortkomt uit het gebruik van het JH Jokot wordt verrekend aan de huurder. Het huishoudelijk reglement dient te allen tijde nageleefd te worden. Deze hangt publiekelijk uit in de gang van het jeugdhuis.</p>
<h2>Drank</h2>
<p>Alle dranken die worden aangeboden in het assortiment van het JH Jokot dienen afgenomen te worden van het jeugdhuis. Enkel cava, wijn en/of champagne is zelf te voorzien, waar wij wel €3 kurkgeld per geopende fles van 75cl of equivalent indien er andere verpakkingseenheden worden gehanteerd voor aanrekenen.</p>
<p>Vaten die gesloten blijven worden niet aangerekend, te verstaan dat de plastiek sluiting niet geopend is. Een vat wordt niet gewogen, vanaf dat deze is aangesloten/geopend wordt de volledige prijs aangerekend.</p>
<p>Bij afname van vaten voorzien wij een beperkte stock (3 bakken) aan flesjes, indien het niet meer de moeite is om een nieuw vat aan te sluiten, kan u hier gebruik van maken. Deze worden standaard opgenomen in de telling.</p>
<p>Mogelijkheid tot verkoop/consumeren van sterke dranken zoals cocktails of dergelijke is niet toegelaten. Ongeacht of de aanvraag voor een evenement met het schenken van sterke dranken is goedgekeurd. Deze afspraak is gemaakt met de gemeente dat wij voor komende periode dit niet toelaten in het jeugdhuis.</p>
<h2>Geluidsnorm</h2>
<p>Wij hebben een afspraak met de gemeente dat de maximaal toegelaten geluidsnorm 90dB is. Deze zal te allen tijde nageleefd worden. Een afwijking hiervan dient door de huurder zelf aangevraagd te worden bij de gemeente.</p>
<h2>Unisono</h2>
<p>Unisono (billijke vergoeding en Sabam) is voorzien vanuit het jeugdhuis. Indien u een coverband boekt die covers brengen van bestaande nummers is het de verantwoordelijkheid van de huurder om hiervoor het nodige te doen bij Unisono, dit zit niet mee inbegrepen in ons contract met Unisono.</p>
<h2>Opkuis</h2>
<p>Het opkuisen van de zaal achteraf is de taak van de huurder. Dit houdt in: het opkuisen van de gebruikte zaal, koer, gang en wc’s. Hieronder te verstaan zowel vegen als met nat. Hiervoor zullen de nodige middelen ter beschikking worden gesteld.</p>
<h2>Decoratie</h2>
<p>Inkleding van de zaal is mogelijk de dag voordien in overleg met de permanentieverantwoordelijke vanuit het Jokot. Wij voorzien dan een kaart (badge) zodat de huurder tijdig binnen kan om alles in te kleden. Tenzij er iemand van de vrijwilligers zich dan al reeds bevindt in het jeugdhuis.</p>
<h2>Contact</h2>
<p>{contact}</p>
HTML,
        ]);

        ContractTemplate::query()->firstOrCreate(['type' => ContractType::CupLoan], [
            'title' => 'Overeenkomst uitlenen wisselbekers',
            'body' => <<<'HTML'
<p><strong>Ondergetekenden</strong></p>
<p>{verhuurder}, rechtsgeldig vertegenwoordigd door {vertegenwoordiger}, gevestigd te Landbouwstraat 145/3, 2845 Niel, hierna te noemen verhuurder,</p>
<p>{huurder}, rechtsgeldig vertegenwoordigd door {huurder_vertegenwoordiger}, gevestigd te {huurder_adres}, hierna te noemen huurder,</p>
<h2>Uitleenvoorwaarden</h2>
<h3>Artikel 1. Uitgeleende zaken</h3>
<p>Deze overeenkomst heeft betrekking op de uitleen van <strong>{aantal_bekers}</strong> herbruikbare bekers, in <strong>{aantal_bakken}</strong> doorzichtige bakken, hierna te noemen Materiaal. Het Materiaal blijft te allen tijde eigendom van de verhuurder.</p>
<h3>Artikel 2. Duur van de overeenkomst</h3>
<p>De verhuurder leent het Materiaal aan de huurder voor de periode van <strong>{van}</strong> tot en met <strong>{tot}</strong>.</p>
<h3>Artikel 3. Levering en retour</h3>
<p>De huurder draagt zelf zorg voor het ophalen van het Materiaal bij de verhuurder. Bij beëindiging van de overeenkomst zorgt de huurder dat hij het Materiaal afgewassen, proper en droog terugbrengt naar de verhuurder.</p>
<h3>Artikel 4. Staat van het Materiaal</h3>
<p>De huurder is verplicht bij de ophaling van het Materiaal deze grondig te inspecteren op gebreken dan wel het verwachtingspatroon daarvan. De staat van het Materiaal wordt ter plaatse bij ophaling en retour nagekeken en geteld.</p>
<h3>Artikel 5. Verplichtingen lener</h3>
<p>De huurder is verplicht het gehuurde Materiaal:</p>
<ul>
<li>overeenkomstig de bedienings- en overige voorschriften o.a. ten aanzien van toezicht, die aan de huurder bij ophaling van de goederen zijn medegedeeld en/of overhandigd, te behandelen;</li>
<li>geen veranderingen aan het Materiaal aan te brengen;</li>
<li>onderverhuur en beschikbaarstelling aan derden uitsluitend met schriftelijke toestemming van de verhuurder te doen geschieden.</li>
</ul>
<h3>Artikel 6. Omgang</h3>
<p>De huurder draagt zorg voor het volgende:</p>
<ul>
<li>het verzorgd gebruik van het Materiaal en enkel datgene waar het voor bestemd is;</li>
<li>de opslag van het Materiaal.</li>
</ul>
<h3>Artikel 7. Onderhoud en diefstal</h3>
<p>Het Materiaal moet de huurder zelf afwassen. Bij diefstal/missende en/of vuile herbruikbare bekers wordt er per beker {prijs_beker} aangerekend. Bij diefstal of kapotte bak (hierin te verstaan: een slechtere staat dan dat de bak was opgehaald) wordt er per bak {prijs_bak} aangerekend.</p>
<h3>Artikel 8. Schade</h3>
<p>Alle bekers zijn voor het uitlenen nagekeken. Indien er schade bij ophaling wordt vastgesteld door de huurder, dient dit ter plaatse gemeld te worden aan de verhuurder. Bij bekers die kapot gaan tijdens het gebruik, geldt het vermelde bedrag uit artikel 7 Onderhoud en diefstal.</p>
<h3>Artikel 9. Huurprijs en eindfactuur</h3>
<p>De huurprijs ongeacht het aantal bekers bedraagt {huurprijs} en dient nadat de factuur per mail verstuurd is binnen de 30 werkdagen betaald te worden op rekeningnummer {iban}.</p>
<h3>Artikel 10. Wijzigingen</h3>
<p>Wijzigingen en aanvullingen van deze overeenkomst zijn slechts geldig indien schriftelijk overeengekomen. Mondelinge nevenafspraken, ook als zij voor de afsluiting van deze overeenkomst zijn gemaakt, zijn niet geldig voor zover zij met de bovenstaande bepalingen in strijd zijn.</p>
<h3>Artikel 11. Toepasselijk recht</h3>
<p>Op alle door de verhuurder gesloten overeenkomsten en/of verrichte handelingen is uitsluitend het Belgische recht van toepassing.</p>
<p>Aldus overeengekomen en elektronisch ondertekend.</p>
HTML,
        ]);
    }

    private function pages(): void
    {
        Page::query()->firstOrCreate(['slug' => 'wisselbekers'], [
            'title' => 'Wisselbekers',
            'menu_order' => 4,
            'show_in_menu' => false,
            'is_published' => true,
            'meta_description' => 'Herbruikbare bekers (wisselbekers) lenen bij JH Jokot voor je event.',
            'blocks' => [
                ['type' => 'text', 'data' => ['content' => '<p>Organiseer je zelf een event? Je kan bij ons herbruikbare bekers (wisselbekers) lenen, in doorzichtige bakken. Zo vermijd je afval van wegwerpbekers.</p>'
                    .'<ul><li>Huurprijs: <strong>€15</strong>, ongeacht het aantal bekers</li><li>Missende of vuile bekers: <strong>€0,25</strong> per beker</li><li>Verloren of beschadigde bak: <strong>€25</strong> per bak</li></ul>'
                    .'<p>Je haalt de bekers zelf op en brengt ze afgewassen, proper en droog terug. Na je aanvraag krijg je een overeenkomst om online te ondertekenen.</p>']],
                ['type' => 'cup_loan_form', 'data' => ['heading' => 'Wisselbekers aanvragen']],
            ],
        ]);
    }
}
