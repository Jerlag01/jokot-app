<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Support\Permissions;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The scanned invoice, from the private disk, only for users with the purchases permission.
 * Shown inline so PDFs and photos open in the browser.
 */
class PurchaseInvoiceFileController extends Controller
{
    public function __invoke(PurchaseInvoice $purchaseInvoice): StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasPermission(Permissions::PURCHASES), 403);
        abort_unless($purchaseInvoice->file_path && Storage::disk('local')->exists($purchaseInvoice->file_path), 404);

        $name = $purchaseInvoice->file_name ?: basename($purchaseInvoice->file_path);

        return Storage::disk('local')->response($purchaseInvoice->file_path, $name, [
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
