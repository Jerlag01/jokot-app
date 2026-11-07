<?php

namespace App\Http\Controllers;

use App\Models\Insurance;
use App\Support\Permissions;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An insurance document from the private disk, only for users with the insurances permission.
 */
class InsuranceDocumentController extends Controller
{
    public function __invoke(Insurance $insurance, int $index): StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasPermission(Permissions::INSURANCES), 403);

        $path = array_values($insurance->documents ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $name = $insurance->documentName($path);

        return Storage::disk('local')->response($path, $name, [
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
