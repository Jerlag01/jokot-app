<?php

namespace Database\Seeders;

use App\Models\OpeningHour;
use App\Models\Page;
use App\Models\Product;
use App\Models\RentalExtra;
use App\Models\RentalRate;
use Illuminate\Database\Seeder;

/**
 * The content of the original jokot.be (WordPress, Nov 2026). Safe to re-run: existing rows are left alone,
 * so edits made in the admin site are never overwritten.
 */
class JokotContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->openingHours();
        $this->rentalRates();
        $this->rentalExtras();
        $this->drinks();
        $this->pages();
    }

    private function openingHours(): void
    {
        $hours = [
            1 => [true, null, null, null],
            2 => [true, null, null, null],
            3 => [false, '20u', '23u', null],
            4 => [true, null, null, 'Open in de vakanties'],
            5 => [false, '20u', '02u', null],
            6 => [false, '20u', '02u', null],
            7 => [true, null, null, 'Open in de vakanties'],
        ];

        foreach ($hours as $weekday => [$closed, $opens, $closes, $note]) {
            OpeningHour::query()->firstOrCreate(['weekday' => $weekday], ['is_closed' => $closed, 'opens_at' => $opens, 'closes_at' => $closes, 'note' => $note]);
        }
    }

    private function rentalRates(): void
    {
        $rates = [
            ['Jongeren/Jeugd (-26 jaar)/Jeugdverenigingen', 150, 200],
            ['Verenigingen (socio-cultureel, oudercomités, …), niet-jeugd (+26)', 250, 200],
            ['Privaat/Bedrijf', 400, 200],
        ];

        foreach ($rates as $sort => [$name, $price, $deposit]) {
            RentalRate::query()->firstOrCreate(['name' => $name], ['price' => $price, 'deposit' => $deposit, 'sort' => $sort]);
        }
    }

    private function rentalExtras(): void
    {
        $extras = [
            ['DJ Installatie Nexus 2', 225, 75],
            ['DJ Installatie Nexus 3', 250, 100],
        ];

        foreach ($extras as $sort => [$name, $price, $deposit]) {
            RentalExtra::query()->firstOrCreate(['name' => $name], ['price' => $price, 'deposit' => $deposit, 'sort' => $sort]);
        }
    }

    private function drinks(): void
    {
        $crate = 'bak van 24 flesjes';
        $drinks = [
            ['Cola 20cl', $crate, null, true],
            ['Cola Zero 20cl', $crate, null, true],
            ['Fanta 20cl', $crate, null, true],
            ['Ice Tea 20cl', $crate, null, true],
            ['Ice Tea Green 20cl', $crate, null, true],
            ['Plat water 20cl', $crate, null, true],
            ['Bruiswater 20cl', $crate, null, true],
            ['Pils 25cl', $crate, null, true],
            ['Vat 20L', null, null, true],
            ['Vat 50L', null, null, true],
            ['Kriek 33cl', $crate, null, true],
            ['Duvel', $crate, null, true],
            ['La Chouffe Blond', $crate, null, true],
            ['Vedett', $crate, null, true],
            ['Carlsberg 0%', $crate, null, true],
            ['Cava Mas D’Ancosa', '75cl', null, true],
        ];

        foreach ($drinks as $sort => [$name, $package, $barPrice, $orderable]) {
            Product::query()->firstOrCreate(
                ['name' => $name],
                ['package' => $package, 'bar_price' => $barPrice, 'is_orderable' => $orderable, 'sort' => $sort],
            );
        }
    }

    private function pages(): void
    {
        $this->page(Page::HOME_SLUG, 'Home', 0, false, 'JH Jokot, het jeugdhuis van Niel. Café-avonden, feestjes, optredens en een zaal om te huren.', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Welkom bij', 'heading' => 'JH Jokot', 'text' => 'Het jeugdhuis van Niel', 'image' => 'images/zaal-rook.jpg', 'size' => 'large', 'button_label' => 'Meer info', 'button_url' => '/info']],
            ['type' => 'cta', 'data' => ['heading' => 'Verhuren', 'text' => 'Je event bij ons organiseren? Kan zeker!', 'image' => 'images/fuif.jpg', 'button_label' => 'Huuraanvraag', 'button_url' => '/verhuren#huuraanvraag']],
            ['type' => 'events', 'data' => ['heading' => 'Agenda', 'limit' => 6]],
            ['type' => 'opening_hours', 'data' => ['heading' => 'Openingsuren']],
        ]);

        $this->page('info', 'Info', 1, true, 'Over JH Jokot, openingsuren en contactgegevens.', [
            ['type' => 'text', 'data' => ['content' => '<p>Het Jeugdhuis was oorspronkelijk op een andere locatie. In 2016 is het Jeugdhuis heropgebouwd in de Landbouwstraat 145 te Niel. Dit gloednieuwe gebouw heeft al heel veel meegemaakt. Feestjes, evenementen, caféavonden, workshops, muzikale activiteiten en afhuringen.</p>']],
            ['type' => 'opening_hours', 'data' => ['heading' => 'Onze openingsuren zijn:']],
            ['type' => 'text', 'data' => ['content' => '<p>Wil je met je band eens komen optreden? Stuur dan gerust een mailtje naar <a href="mailto:bands@jokot.be">bands@jokot.be</a></p>'
                .'<p>JH Jokot is een jeugdhuiswerking in privaatrechtelijke vorm, Jeugdontmoetingscentrum Niel vzw of kortweg JOC Niel vzw.</p>'
                .'<p>Zeteladres: Landbouwstraat 145 bus 3, 2845 Niel<br>Ondernemingsnummer: 1013.578.536</p>']],
        ]);

        $this->page('verhuren', 'Verhuren', 2, true, 'Het jeugdhuis huren voor je feest of event: tarieven, voorwaarden, kalender en huuraanvraag.', [
            ['type' => 'text', 'data' => ['content' => '<p>Het jeugdhuis doet meer dan enkel feestjes geven en café-avonden organiseren. Je kan namelijk ook het jeugdhuis afhuren. Hieronder vindt u de info en voorwaarden die hieraan verbonden zijn:</p>'
                .'<blockquote><p>Vergeet voor je een datum aanvraagt zeker onze <a href="#kalender">kalender</a> niet even te bekijken. Zo wordt vermeden dat je je event plant op een datum dat we zelf al iets organiseren.</p></blockquote>'
                .'<h3>Activiteiten</h3><p>Er kunnen enkel activiteiten plaatsvinden in het JOC die verenigbaar zijn met onze doelstellingen. Voor activiteiten die potentieel overlast kunnen bezorgen zoals fuiven, optredens en dergelijke is de toestemming van het bestuur nodig. Het sluitingsuur 02:00 op vrijdag en zaterdag dient gerespecteerd te worden.</p>'
                .'<h3>Reservatie</h3><p>De reservatie gebeurt maximum 6 maanden en minimum 1 maand op voorhand. Voorrang wordt verleend aan de eigen werking. De reservatie gebeurt via het <a href="#huuraanvraag">contactformulier onderaan de pagina</a>.</p>']],
            ['type' => 'rental_rates', 'data' => ['heading' => 'Tarieven']],
            ['type' => 'rental_extras', 'data' => ['heading' => 'DJ-installatie', 'intro' => 'Het Jokot heeft zelf geen DJ-installatie, maar hier kunnen wij wel voor zorgen als u dit wenst via een externe partner.']],
            ['type' => 'text', 'data' => ['content' => '<h3>Lichtinstallatie</h3><p>De lichtinstallatie bestaat uit 6 movingheads en 20 ledparren. Deze worden hoofdzakelijk bediend vanuit de computer van het JOC, maar men kan zeker een eigen lichttafel meebrengen en deze daar op aansluiten. Het JOC voorziet dan een lichtplan welke adressen er in gebruik zijn.</p><p>Het is niet toegestaan de lichtinstallatie aan te passen. Men mag wel verlichting bijplaatsen, maar hier is dan de huurder verantwoordelijk voor.</p>'
                .'<h3>Dranken</h3><p>Alle dranken worden afgenomen van het jeugdhuis aan zijn gangbare tarieven (€1,50 voor frisdranken, €1,80 voor pils en €2,50 voor fruitbier).</p><p>Wenst u toch andere dranken die niet in ons assortiment vallen, dan mag u deze zelf voorzien. Let wel op dat hier kurkgeld op gerekend wordt aan de hand van het artikel hieronder. Met uitzondering van koffie en thee.</p><p>Er wordt in geen geval sterke drank geschonken of drank mee naar buiten genomen. Het consumeren van eigen dranken wordt niet toegestaan.</p><p>Bonnen kunnen worden afgenomen aan €1,80* per bon. De afrekening gebeurt op basis van het aantal verbruikte bonnen.</p><p><em>* De bonnenprijs varieert samen met de prijs van de pils.</em></p>'
                .'<h3>Ter aanvulling: kurkgeld</h3><p>Indien de huurder wijnen, champagne of cava wenst te schenken, zal er per geopende fles van 75cl (of equivalent indien er andere verpakkingseenheden worden gehanteerd) een bedrag van 3 euro worden aangerekend. Cava, wijn en/of champagne zijn zelf te voorzien.</p><p>Mogelijkheid tot verkoop/consumeren van sterke dranken zoals cocktails of dergelijke is niet toegelaten. Ongeacht of de aanvraag voor een evenement met het schenken van sterke dranken is goedgekeurd. In onderling overleg met het gemeentebestuur hebben wij beslist om dit momenteel niet toe te laten in het jeugdhuis.</p>'
                .'<h3>Opkuis na het evenement</h3><p>De beschikbaar gestelde lokalen zullen na gebruik terug in de staat worden gesteld waarin ze voor het gebruik werden aangetroffen.</p>'
                .'<h3>Toegang</h3><p>Alle leden van het jeugdhuis hebben recht op min. 30% korting op de toegangsprijs. Dit lidmaatschap is enkel geldig bij voorlegging van een geldige lidkaart.</p>'
                .'<h3>Unisono (auteursrecht en billijke vergoeding)</h3><p>Unisono (Sabam en billijke vergoeding) is/zijn al voorzien voor het jeugdhuis. Voor optredens met livebands die covers brengen van bestaande nummers is het de verantwoordelijkheid van de huurder om dit zelf aan te geven bij Unisono en de vergunning hiervoor aan te vragen.</p><p>De huurder is zelf verantwoordelijk voor de aanvraag in het evenementenloket van de gemeente indien men een fuif of optreden wil organiseren. Ook als men wil afwijken van de gebruikelijke openingsuren (20u-2u). Indien de huurder geen geldige toelating kan voorleggen dient de huurder zich te houden aan de openingsuren van het jeugdhuis als ook de geluidsnorm.</p>'
                .'<h3>Ruimtebeschrijving</h3><p>De lokalen zijn gelegen in het jeugdontmoetingscentrum JOC Niel te Landbouwstraat 145/3, 2845 Niel. In het JOC zijn een instuifruimte en een repetitieruimte gelegen.</p><p>De instuifruimte is de permanente ontmoetingsruimte van jeugdhuis Jokot Niel.</p><ul><li>147m² (11,2m x 13,14m)</li><li>Geschikt voor: café- en themavonden, kleine feestjes en optredens</li><li>Max. 350 personen (lege zaal)</li><li>Beschikbaar voor gebruik door iedereen</li></ul><p>De repetitieruimte wordt beheerd via de gemeente Niel, deze kan u <a href="https://webshopniel.recreatex.be/Bookings/Overview">hier</a> aanvragen.</p>'
                .'<h3>Inbegrepen in het gebruik van de instuif</h3><ul><li>tafels, stoelen, barkrukken, hoge tafels en zetels</li><li>vaste zaalverlichting: spots</li><li>muziekinstallatie (via pc)</li></ul><p>Voor meer informatie kunt u op aanvraag het volledige gebruiksreglement bekomen bij de Dienst Vrije Tijd (Ridder Berthoutlaan 1, 2845 Niel). Of door ons te contacteren via mail: <a href="mailto:info@jokot.be">info@jokot.be</a></p>']],
            ['type' => 'calendar', 'data' => ['heading' => 'Kalender', 'intro' => 'Groen = vrij. Klik op een vrije datum om ze meteen in het formulier hieronder in te vullen.']],
            ['type' => 'rental_form', 'data' => ['heading' => 'Huuraanvraag', 'intro' => 'Gelieve hier je aanvraag door te sturen indien je de zaal wilt afhuren. Je aanvraag is pas definitief na bevestiging door het bestuur.']],
        ]);

        $this->page('winkel', 'Winkel', 3, true, 'Tickets voor activiteiten van JH Jokot.', [
            ['type' => 'text', 'data' => ['content' => '<p>Hier vind je de tickets voor onze komende activiteiten. Lees zeker ook onze <a href="/algemene-voorwaarden">algemene voorwaarden</a>.</p>']],
            ['type' => 'events', 'data' => ['heading' => '', 'limit' => 12, 'empty_text' => 'Er zijn momenteel geen tickets te koop. Hou onze Facebook en Instagram in de gaten!']],
        ]);

        $terms = [
            'Tickets worden noch terugbetaald, noch geruild voor een ander evenement.',
            'Verlies of diefstal van het ticket behoort tot de verantwoordelijkheid van de koper en geeft geen aanleiding tot vervanging of terugbetaling.',
            'De ticketkoper dient zich te houden aan de Wet betreffende de verkoop van toegangsbewijzen tot evenementen (BS 30 juli 2013).',
            'Wijzigingen in de programmatie geven geen aanleiding tot gehele of gedeeltelijke terugbetaling van het ticket.',
            'De organisator is in geen geval aansprakelijk voor de stopzetting en/of afgelasting van het festival/fuif door overmacht (zoals o.a. ongewone weersomstandigheden, ziekte, overlijden of afwezigheid van een artiest).',
            'Het enige erkende ticketverkoopadres voor evenementen, georganiseerd door JH Jokot is via https://www.jokot.be. Tickets gekocht op straat of via tweedehands veilingsites worden mogelijk geblacklist en geven geen toegang tot het festival/fuif.',
            'Tickets mogen niet voor commerciële of promotionele doeleinden gebruikt of aan derden verschaft worden zonder schriftelijke toestemming van de organisator.',
            'Identiteitscontrole van de koper door de organisator is ten allen tijde mogelijk.',
            'Enkel het ticket dat eerst aangeboden wordt, zal gevalideerd worden. Houders van een gekopieerd ticket zal de toegang en/of validatie worden geweigerd.',
            'Het is uitdrukkelijk verboden enige promotionele of commerciële activiteit te ontplooien op het terrein van het evenement zonder voorafgaande schriftelijke toelating van de organisator.',
            'Behoudens schriftelijke toestemming van de organisator is elk commercieel gebruik van de naam of het logo van het evenement zelf of de aantredende artiesten strikt verboden.',
            'Het uitdelen van flyers, reclamefolders of ander promomateriaal is verboden, tenzij dit voorafgaand schriftelijk is goedgekeurd door de organisator.',
            'De officiële verkoop van t-shirts en souvenirs geschiedt uitsluitend binnen het terrein van het evenement.',
            'Betreding van het terrein impliceert instemming met alle preventieve en controlerende maatregelen die de organisator of de veiligheidsploeg neemt met inbegrip van mogelijke aftasting en onderzoek van tassen.',
            'Het niet naleven van deze maatregelen leidt tot weigering of intrekking van toegangsrecht.',
            'Het is niet toegestaan om specifieke voorwerpen mee te nemen op het terrein, (lijsten te raadplegen op www.jhjokot.be). De bezoeker riskeert inbeslagname door de veiligheidsploegen. Deze lijsten kunnen te allen tijde aangepast of aangevuld worden door de organisatie.',
            'Er worden opnames en foto’s gemaakt tijdens het evenement. De kans bestaat dat de bezoeker wordt gefilmd en gefotografeerd. De bezoeker gaat akkoord met het gebruik van deze beelden door de organisatie en haar mediapartners.',
            'De organisator en eventuele medeorganisatoren kunnen niet aansprakelijk gesteld worden voor eventuele schadegevallen en om het even welk gebeurlijk lichamelijk ongeval op het terrein, de fietsenparking en het parkeerterrein, dit naar aanloop van, tijdens en na het evenement.',
            'De artiesten, de organisator en haar medeorganisatoren, de NMBS en De Lijn kunnen niet aansprakelijk worden gesteld voor schade, verlies of diefstal noch voor om het even welk gebeurlijk ongeval.',
            'Aanvullende regelingen i.v.m. verplaatsing naar het evenement en voedingsmiddelen, die de koper op eigen initiatief heeft getroffen, zijn ten laste van de koper. De organisatie is niet aansprakelijk voor vergeefse kosten bovenop de prijs van het/de ticket(s) en de hieraan verbonden reserveringskosten.',
        ];

        $this->page('algemene-voorwaarden', 'Algemene voorwaarden', 9, false, 'Algemene voorwaarden voor tickets en evenementen van JH Jokot.', [
            ['type' => 'text', 'data' => ['content' => '<ul>'.collect($terms)->map(fn (string $line): string => '<li>'.e($line).'</li>')->implode('').'</ul>']],
        ]);
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     */
    private function page(string $slug, string $title, int $order, bool $inMenu, string $description, array $blocks): void
    {
        Page::query()->firstOrCreate(['slug' => $slug], [
            'title' => $title,
            'menu_order' => $order,
            'show_in_menu' => $inMenu,
            'is_published' => true,
            'meta_description' => $description,
            'blocks' => $blocks,
        ]);
    }
}
