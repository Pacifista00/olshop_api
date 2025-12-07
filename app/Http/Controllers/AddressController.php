<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of user addresses retrieved successfully.',
            'data' => AddressResource::collection($addresses)
        ], 200);
    }
    public function store(Request $request)
    {
        // 1. Validasi input
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'street_address' => 'required|string',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'is_default' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            // 2. Tentukan user_id
            $validated['user_id'] = $request->user()->id;

            // 3. Jika alamat baru dijadikan default → reset alamat lain
            if ($request->boolean('is_default')) {
                Address::where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }

            // 4. Create address
            $address = Address::create($validated);

            if (!$address) {
                throw new \Exception("Gagal membuat alamat.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address created successfully.',
                'data' => new AddressResource($address)
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan alamat.',
                // 'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function update(Request $request, Address $address)
    {
        // 1. Pastikan address milik user yang login
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this address.'
            ], 403);
        }

        // 2. Validasi input
        $validated = $request->validate([
            'recipient_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:15',
            'street_address' => 'sometimes|string',
            'city' => 'sometimes|string|max:100',
            'province' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:10',
            'is_default' => 'boolean'
        ]);

        DB::beginTransaction();

        try {

            // 3. Jika update dijadikan default → reset alamat lain
            if ($request->boolean('is_default')) {
                Address::where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }

            // 4. Update address
            $updated = $address->update($validated);

            if (!$updated) {
                throw new \Exception("Gagal mengupdate alamat.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address updated successfully.',
                'data' => new AddressResource($address)
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupdate alamat.',
                'error' => $e->getMessage(), // optional
            ], 500);
        }
    }
    public function destroy(Request $request, Address $address)
    {
        // 1. Pastikan address milik user yang login
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to this address.'
            ], 403);
        }

        DB::beginTransaction();

        try {

            $wasDefault = $address->is_default;

            // 2. Hapus address
            if (!$address->delete()) {
                throw new \Exception("Gagal menghapus alamat.");
            }

            // 3. Jika address yang dihapus adalah default → set default baru
            if ($wasDefault) {
                $nextAddress = Address::where('user_id', $request->user()->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($nextAddress) {
                    $nextAddress->update(['is_default' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Address deleted successfully.',
                'data' => null
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus alamat.',
                'error' => $e->getMessage(), // optional
            ], 500);
        }
    }

}
