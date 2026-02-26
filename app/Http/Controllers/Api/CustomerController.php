<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::where('is_active', true)->get();
        return response()->json(['success' => true, 'data' => $customers]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'type' => 'required|in:distributor,agent,retail',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Auto-generate kode customer sederhana
        $count = Customer::count() + 1;
        $kode = 'CUST-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $customer = Customer::create(array_merge(
            $request->all(),
            ['kode_customer' => $kode]
        ));

        return response()->json(['success' => true, 'data' => $customer], 201);
    }

    // Menampilkan detail 1 customer (untuk halaman detail/edit)
    public function show($id)
    {
        $customer = Customer::find($id);
        if (!$customer) return response()->json(['message' => 'Not Found'], 404);
        return response()->json(['success' => true, 'data' => $customer]);
    }

    // Update data customer
    public function update(Request $request, $id)
{
    $customer = Customer::find($id);

    if (!$customer) {
        return response()->json(['success' => false, 'message' => 'Customer tidak ditemukan'], 404);
    }

    $request->validate([
        'name' => 'sometimes|required|string|max:255',
        'address' => 'sometimes|required|string',
    ]);

    $customer->update($request->all());

    return response()->json([
        'success' => true,
        'message' => 'Data customer berhasil diperbarui',
        'data' => $customer
    ]);
}

    // Hapus data (Soft Delete)
    public function destroy($id)
    {
        $customer = Customer::find($id);
        
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Not Found'], 404);
        }

        $customer->delete(); 
        
        return response()->json([
            'success' => true, 
            'message' => 'Data Customer Berhasil Dihapus Sementara'
        ]);
    }

    public function restore($id)
    {
        $customer = Customer::onlyTrashed()->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak ditemukan di sampah'
            ], 404);
        }

        $customer->restore(); 

        return response()->json([
            'success' => true, 
            'message' => 'Data Customer Berhasil Dikembalikan',
            'data' => $customer
        ]);
    }
}
