<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The page behind the QR on the member card: members keep their own details up to date.
 */
class MemberSelfServiceController extends Controller
{
    private function find(string $token): Member
    {
        return Member::query()->where('token', $token)->with('memberships')->firstOrFail();
    }

    public function edit(string $token): View
    {
        return view('members.edit', ['member' => $this->find($token)]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $member = $this->find($token);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'house_number' => ['nullable', 'string', 'max:20'],
            'box' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [], [
            'first_name' => 'voornaam', 'last_name' => 'naam', 'street' => 'straat', 'house_number' => 'huisnummer',
            'postal_code' => 'postcode', 'city' => 'gemeente', 'phone' => 'telefoon',
        ]);

        $member->update([...$data, 'details_updated_at' => now()]);

        return redirect()->route('members.edit', $token)->with('saved', true);
    }
}
