<?php

namespace App\Http\Controllers;

use App\Mail\ContractMail;
use App\Models\Contract;
use App\Support\Contracts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Reading and signing a contract online, reached through the unguessable link in the mail.
 */
class ContractController extends Controller
{
    private function find(string $token): Contract
    {
        return Contract::query()->where('token', $token)->with('contractable')->firstOrFail();
    }

    public function show(string $token): View
    {
        $contract = $this->find($token);

        return view('contracts.show', ['contract' => $contract, 'body' => $contract->renderedBody()]);
    }

    public function sign(Request $request, string $token, Contracts $contracts): RedirectResponse
    {
        $contract = $this->find($token);

        if ($contract->isSigned()) {
            return redirect()->route('contracts.show', $token);
        }

        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string', 'max:700000'],
            'agree' => ['accepted'],
        ], [
            'signature.required' => 'Zet je handtekening in het vak.',
            'agree.accepted' => 'Vink aan dat je akkoord gaat met de overeenkomst.',
        ], ['signer_name' => 'naam']);

        try {
            $contracts->sign($contract, $data['signer_name'], $data['signature'], $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['signature' => $exception->getMessage()]);
        }

        try {
            $copy = new ContractMail($contract->refresh(), signedCopy: true);
            Mail::to($contract->contractable->contractSignerEmail())->send($copy);

            if (filled($board = settings()->get('notification_email'))) {
                Mail::to($board)->send(new ContractMail($contract, signedCopy: true));
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('contracts.show', $token)->with('signed', true);
    }

    public function pdf(string $token, Contracts $contracts): Response
    {
        $contract = $this->find($token);

        return response($contracts->pdfContents($contract), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$contracts->filename($contract).'"',
        ]);
    }
}
