<?php

namespace App\Support;

use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Member cards in ID-card format (ISO/IEC 7810 ID-1, 85.6 x 54 mm) and the member list export.
 */
class MemberCards
{
    /** ID-1 card in PDF points (1 mm = 72 / 25.4 pt). */
    public const CARD_PAPER = [0, 0, 242.65, 153.07];

    /**
     * @param  Collection<int, Member>  $members
     * @param  string  $layout  "card": one card per page (card printer), "a4": ten per A4 sheet to cut out
     */
    public function pdf(Collection $members, int $year, string $layout = 'a4'): DomPdf
    {
        $cards = $members->map(fn (Member $member): array => [
            'member' => $member,
            'qr' => Qr::pngDataUri($member->selfServiceUrl(), 5),
        ]);

        $pdf = Pdf::loadView('pdf.member-cards', [
            'cards' => $cards,
            'year' => $year,
            'layout' => $layout,
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/jokot-logo-wit.png'))),
        ]);

        return $layout === 'card' ? $pdf->setPaper(self::CARD_PAPER) : $pdf->setPaper('a4');
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    public function xlsx(Collection $members, int $year): string
    {
        $path = tempnam(sys_get_temp_dir(), 'leden').'.xlsx';
        $options = new Options;
        $options->setColumnWidthForRange(18, 1, 12);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Leden '.$year);
        $writer->addRow(Row::fromValues(['Lidnummer', 'Kaartnummer', 'Naam', 'Voornaam', 'Straat', 'Nr.', 'Bus', 'Postcode', 'Gemeente', 'E-mail', 'Telefoon', 'Lidgeld '.$year], (new Style)->setFontBold()));

        foreach ($members as $member) {
            $writer->addRow(Row::fromValues([
                $member->member_number, $member->card_number, $member->last_name, $member->first_name, $member->street, $member->house_number, $member->box,
                $member->postal_code, $member->city, $member->email, $member->phone,
                (float) ($member->memberships->firstWhere('year', $year)?->amount ?? 0),
            ]));
        }

        $writer->close();

        return $path;
    }
}
