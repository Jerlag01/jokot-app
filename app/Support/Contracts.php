<?php

namespace App\Support;

use App\Contracts\HasContract;
use App\Enums\ContractStatus;
use App\Mail\ContractMail;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Contracts for rentals and cup loans: drawn up from the template, sent as a signing link, signed online.
 * Signed files live on the private "local" disk, never in public storage.
 */
class Contracts
{
    /**
     * The current contract, or a new draft. A signed contract is kept; call again after a reset to start over.
     */
    public function forRecord(Model&HasContract $record): Contract
    {
        $existing = $record->contract()->first();

        if ($existing) {
            return $existing;
        }

        $contract = new Contract(['type' => $record->contractType()]);
        $contract->contractable()->associate($record);
        $contract->save();

        return $contract;
    }

    public function send(Contract $contract): void
    {
        if ($contract->isSigned()) {
            throw new RuntimeException('Dit contract is al ondertekend.');
        }

        Mailing::ensureEnabled();

        Mail::to($contract->contractable->contractSignerEmail())->send(new ContractMail($contract));

        $contract->update(['status' => ContractStatus::Sent, 'sent_at' => now()]);
    }

    /**
     * Stores the drawn signature, freezes the text and saves the signed PDF.
     */
    public function sign(Contract $contract, string $signerName, string $signatureDataUri, ?string $ip): void
    {
        if ($contract->isSigned()) {
            throw new RuntimeException('Dit contract is al ondertekend.');
        }

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signatureDataUri, $match)) {
            throw new InvalidArgumentException('Ongeldige handtekening.');
        }

        $png = base64_decode($match[1], true);

        if ($png === false || strlen($png) > 500_000 || ! str_starts_with($png, "\x89PNG")) {
            throw new InvalidArgumentException('Ongeldige handtekening.');
        }

        DB::transaction(function () use ($contract, $signerName, $png, $ip): void {
            $signaturePath = "contracts/signatures/{$contract->id}.png";
            Storage::disk('local')->put($signaturePath, $png);

            $contract->forceFill([
                'body' => $contract->renderedBody(),
                'status' => ContractStatus::Signed,
                'signed_at' => now(),
                'signer_name' => $signerName,
                'signer_ip' => $ip,
                'signature_path' => $signaturePath,
            ])->save();

            $pdfPath = "contracts/{$contract->id}-{$contract->token}.pdf";
            Storage::disk('local')->put($pdfPath, $this->pdf($contract)->output());
            $contract->forceFill(['pdf_path' => $pdfPath])->save();
        });
    }

    /**
     * The signed PDF when there is one, otherwise rendered now (unsigned).
     */
    public function pdf(Contract $contract): DomPdf
    {
        $signature = $contract->signature_path && Storage::disk('local')->exists($contract->signature_path)
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($contract->signature_path))
            : null;

        return Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'body' => $contract->renderedBody(),
            'signature' => $signature,
            'settings' => settings(),
        ])->setPaper('a4');
    }

    public function pdfContents(Contract $contract): string
    {
        if ($contract->pdf_path && Storage::disk('local')->exists($contract->pdf_path)) {
            return Storage::disk('local')->get($contract->pdf_path);
        }

        return $this->pdf($contract)->output();
    }

    public function filename(Contract $contract): string
    {
        $reference = $contract->contractable->reference ?? $contract->id;

        return 'contract-'.$reference.($contract->isSigned() ? '-ondertekend' : '').'.pdf';
    }

    /**
     * Removes the contract altogether, signed or not, with its signature and PDF (e.g. the rental was called off).
     */
    public function delete(Contract $contract): void
    {
        foreach ([$contract->signature_path, $contract->pdf_path] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }

        $contract->delete();
    }

    /**
     * Throws away the signature so the contract can be adjusted and signed again.
     */
    public function reset(Contract $contract): void
    {
        foreach ([$contract->signature_path, $contract->pdf_path] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }

        $contract->forceFill([
            'status' => ContractStatus::Draft, 'body' => null, 'signed_at' => null, 'signer_name' => null,
            'signer_ip' => null, 'signature_path' => null, 'pdf_path' => null, 'sent_at' => null,
        ])->save();
    }
}
