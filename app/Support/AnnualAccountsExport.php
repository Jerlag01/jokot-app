<?php

namespace App\Support;

use App\Enums\AnnualLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The yearly overview as an Excel file (same layout as the workbook, plus the underlying items per year)
 * or as a PDF.
 */
class AnnualAccountsExport
{
    public function __construct(private AnnualAccounts $accounts) {}

    /**
     * Writes the workbook to a temporary file and returns its path.
     *
     * @param  list<int>  $years
     */
    public function xlsx(array $years): string
    {
        $overview = $this->accounts->overview($years);
        $path = tempnam(sys_get_temp_dir(), 'jaarrekening').'.xlsx';

        $options = new Options;
        $options->setColumnWidth(52, 1);
        $options->setColumnWidthForRange(16, 2, 1 + 2 * count($years));

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Overzicht Jaarrekening');

        $money = (new Style)->setFormat('#,##0.00');
        $bold = (new Style)->setFontBold();
        $boldMoney = (new Style)->setFontBold()->setFormat('#,##0.00');
        $header = (new Style)->setFontBold()->setBackgroundColor('111111')->setFontColor('FFFFFF');

        $writer->addRow(Row::fromValues([mb_strtoupper((string) settings()->get('legal_name'))], $bold));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['BUDGET', ...collect($years)->flatMap(fn (int $year): array => [(string) $year, (string) $year])->all()], $header));
        $writer->addRow(Row::fromValues(['', ...collect($years)->flatMap(fn (): array => ['Budget/Schatting', 'Jaarrekening'])->all()], $header));

        foreach (AnnualAccounts::layout() as $row) {
            $values = [$row['label']];

            if ($row['type'] === 'heading' || $row['type'] === 'section') {
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues($values, $bold));

                continue;
            }

            foreach ($years as $year) {
                [$budget, $actual] = $this->cells($overview[$year], $row);
                $values[] = $budget;
                $values[] = $actual;
            }

            $writer->addRow(Row::fromValues($values, $row['type'] === 'line' ? $money : $boldMoney));
        }

        foreach ($years as $year) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Details '.$year);
            $writer->addRow(Row::fromValues(['Lijn', 'Datum', 'Bron', 'Omschrijving', 'Bedrag'], $header));

            foreach ($this->accounts->actuals($year) as $key => $line) {
                foreach ($line['items'] as $item) {
                    $writer->addRow(Row::fromValues([AnnualLine::from($key)->optionLabel(), $item['date'] ?? '', $item['source'], $item['description'], $item['amount']]));
                }
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<int>  $years
     */
    public function pdf(array $years): DomPdf
    {
        return Pdf::loadView('pdf.annual-accounts', [
            'years' => $years,
            'overview' => $this->accounts->overview($years),
            'layout' => AnnualAccounts::layout(),
            'export' => $this,
            'settings' => settings(),
        ])->setPaper('a4', 'landscape');
    }

    /**
     * The budget and actual value of one layout row for one year.
     *
     * @param  array<string, mixed>  $year
     * @param  array{type: string, label: string, key?: string}  $row
     * @return array{0: float, 1: float}
     */
    public function cells(array $year, array $row): array
    {
        return match ($row['type']) {
            'line' => [$year['budget'][$row['key']] ?? 0.0, $year['actual'][$row['key']] ?? 0.0],
            'carried' => [$year['carried'], $year['carried']],
            'cumulated' => [$year['budget_cumulated'], $year['cumulated']],
            default => [$year['budget_totals'][$row['key']], $year['actual_totals'][$row['key']]],
        };
    }
}
