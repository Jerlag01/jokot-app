{{-- Preview of the scanned incoming invoice (served from the private disk by PurchaseInvoiceFileController). --}}
@php
    $record = $getRecord();
    $url = route('purchase-invoices.file', $record);
    $isPdf = str_ends_with(strtolower((string) ($record->file_name ?: $record->file_path)), '.pdf');
@endphp
@if ($isPdf)
    <iframe src="{{ $url }}" style="width: 100%; height: 75vh; border: 0; border-radius: .5rem; background: #fff;" title="Factuur"></iframe>
@else
    <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="Factuur" style="max-width: 100%; max-height: 75vh; border-radius: .5rem;"></a>
@endif
<p style="margin-top: .5rem; font-size: .85rem;"><a href="{{ $url }}" target="_blank" style="text-decoration: underline;">{{ $record->file_name ?: 'Bestand openen' }}</a></p>
