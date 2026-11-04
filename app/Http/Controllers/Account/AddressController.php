<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    private const RULES = [
        'label' => 'nullable|string|max:50',
        'recipient_name' => 'required|string|max:255',
        'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
        'line1' => 'required|string|max:255',
        'line2' => 'nullable|string|max:255',
        'barangay' => 'nullable|string|max:255',
        'city' => 'required|string|max:255',
        'province' => 'required|string|max:255',
        'postal_code' => 'nullable|string|max:10',
        'is_default' => 'sometimes|boolean',
    ];

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate(self::RULES);
        $user = $request->user();

        // The first address is the default one
        $isFirst = ! $user->addresses()->exists();
        $makeDefault = $isFirst || ! empty($data['is_default']);
        $data['is_default'] = $makeDefault;

        if ($makeDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($data);

        return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(self::RULES);
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        if (! empty($data['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        } else {
            // never leave the account without a default address
            $data['is_default'] = $address->is_default;
        }

        $address->update($data);

        return response()->json($address->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;

        $address->delete();

        if ($wasDefault) {
            $next = $user->addresses()->orderBy('id')->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json(['message' => 'Address removed.']);
    }
}
