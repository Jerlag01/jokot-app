<?php

namespace App\Support;

use App\Models\ProductServing;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The price calculation as an Excel file, in the column order of "Factorberekening JH Niel".
 */
class PricingExport
{
    public function __construct(private Pricing $pricing) {}

    public function xlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'factor').'.xlsx';
        $summary = $this->pricing->summary();

        $options = new Options;
        $options->setColumnWidth(32, 1);
        $options->setColumnWidthForRange(14, 2, 15);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Factorberekening');

        $bold = (new Style)->setFontBold();
        $header = (new Style)->setFontBold()->setBackgroundColor('111111')->setFontColor('FFFFFF')->setShouldWrapText();
        $money = (new Style)->setFormat('#,##0.00');

        $writer->addRow(Row::fromValues(['Factorberekening '.settings()->siteName(), 'Stand '.now()->format('d/m/Y')], $bold));
        $writer->addRow(Row::fromValues([
            'Gemiddelde factor', $summary['current'] !== null ? round($summary['current'], 4) : '',
            'Met nieuwe prijzen', $summary['proposed'] !== null ? round($summary['proposed'], 4) : '',
            'Doelfactor', $this->pricing->targetFactor(),
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Product', 'Verkocht als', 'Verpakkingsprijs excl. btw', 'Aantal per verpakking', 'Eenheidsprijs excl. btw', 'Btw %', 'Btw/verpakking €',
            'Verpakkingsprijs incl. btw', 'Kost/portie incl. btw', 'Verkoopprijs', 'Factor', 'Aanbevolen prijs', 'Nieuwe prijs', 'Nieuwe factor', 'Categorie',
        ], $header));

        foreach ($this->pricing->servings() as $serving) {
            /** @var ProductServing $serving */
            $product = $serving->product;
            $excl = $product->package_price !== null ? (float) $product->package_price : null;
            $vat = (float) $product->vat_rate;
            $servings = (float) $serving->servings_per_package;
            $factor = $this->pricing->factor($serving, $serving->price);
            $newFactor = $serving->proposed_price !== null ? $this->pricing->factor($serving, $serving->proposed_price) : null;
            $cost = $this->pricing->costPerServing($serving);

            $writer->addRow(Row::fromValues([
                $product->name,
                $serving->label,
                $excl ?? '',
                $servings,
                $excl !== null && $servings > 0 ? round($excl / $servings, 4) : '',
                $vat / 100,
                $excl !== null ? round($excl * $vat / 100, 4) : '',
                $product->packagePriceInclVat() !== null ? round($product->packagePriceInclVat(), 4) : '',
                $cost !== null ? round($cost, 4) : '',
                $serving->price !== null ? (float) $serving->price : '',
                $factor !== null ? round($factor, 4) : '',
                $this->pricing->recommendedPrice($serving) ?? '',
                $serving->proposed_price !== null ? (float) $serving->proposed_price : '',
                $newFactor !== null ? round($newFactor, 4) : '',
                $product->category->getLabel(),
            ], $money));
        }

        $writer->close();

        return $path;
    }
}
