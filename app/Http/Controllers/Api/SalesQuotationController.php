<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesQuotation;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SalesQuotationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tanggal'     => 'required|date',
            'items'       => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:1',
            'items.*.price'      => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            $noQuotation = 'SQ-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $totalPrice = 0;

            // 1. Buat Header
            $quotation = SalesQuotation::create([
                'no_quotation' => $noQuotation,
                'tanggal'      => $request->tanggal,
                'customer_id'  => $request->customer_id,
                'cara_bayar'   => $request->cara_bayar,
                'dp_amount'    => $request->dp_amount ?? 0,
                'created_by'   => Auth::id() ?? 1,
                'status'       => 'draft'
            ]);

            // 2. Buat Items & Hitung Total
            foreach ($request->items as $item) {
                $subtotal = $item['qty'] * $item['price'];
                $quotation->items()->create([
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'price'      => $item['price'],
                    'subtotal'   => $subtotal,
                ]);
                $totalPrice += $subtotal;
            }

            // 3. Update Total di Header
            $quotation->update(['total_price' => $totalPrice]);

            return response()->json(['success' => true, 'message' => 'Penawaran berhasil dibuat', 'data' => $quotation->load('items.product', 'customer')], 201);
        });
    }

    public function index(Request $request)
    {
    $query = SalesQuotation::with(['customer', 'items.product']);

    // Filter berdasarkan rentang tanggal
    if ($request->filled(['tanggal_awal', 'tanggal_akhir'])) {
        $query->whereBetween('tanggal', [$request->tanggal_awal, $request->tanggal_akhir]);
    }

    // Filter berdasarkan status
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    $data = $query->latest()->paginate($request->per_page ?? 10);

    return response()->json([
        'success' => true,
        'data' => $data
    ]);
    }

    /**
     * Menampilkan detail satu Penawaran (GET {id})
     */
    public function show($id)
    {
    $quotation = SalesQuotation::with(['customer', 'items.product'])->find($id);

    if (!$quotation) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $quotation
    ]);
    }

    /**
     * Memperbarui Data Penawaran (PUT {id})
     */
    public function update(Request $request, $id)
    {
    $quotation = SalesQuotation::find($id);

    if (!$quotation) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $request->validate([
        'status'     => 'sometimes|in:draft,sent,approved,rejected',
        'cara_bayar' => 'sometimes|string',
        'notes'      => 'nullable|string',
    ]);

    $quotation->update($request->only(['status', 'cara_bayar', 'notes', 'dp_amount']));

    return response()->json([
        'success' => true,
        'message' => 'Penawaran berhasil diperbarui',
        'data' => $quotation
    ]);
    }

    /**
     * Hapus Penawaran sementara (DELETE {id})
     */
    public function destroy($id)
    {
    $quotation = SalesQuotation::find($id);

    if (!$quotation) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $quotation->delete(); // Soft Delete

    return response()->json([
        'success' => true,
        'message' => 'Penawaran berhasil dihapus'
    ]);
    }

    /**
    * Mengembalikan Penawaran dari sampah (POST {id}/restore)
    */
    public function restore($id)
    {
    $quotation = SalesQuotation::onlyTrashed()->find($id);

    if (!$quotation) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
    }

    $quotation->restore();

    return response()->json([
        'success' => true,
        'message' => 'Penawaran berhasil dikembalikan',
        'data' => $quotation
    ]);
    }

    /**
    * Ubah Penawaran jadi Spk
    */
    public function convertToSpk($id)
{
    $quotation = SalesQuotation::with('items')->find($id);

    if (!$quotation) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    // Hanya accepted yang boleh convert
    if ($quotation->status !== 'approved') {
        return response()->json([
            'success' => false,
            'message' => 'Hanya penawaran dengan status ACCEPTED yang bisa dikonversi ke SPK.'
        ], 400);
    }

    //  Cegah double convert
    if ($quotation->salesOrder) {
        return response()->json([
            'success' => false,
            'message' => 'Penawaran ini sudah pernah dikonversi menjadi SPK.'
        ], 400);
    }

    return DB::transaction(function () use ($quotation) {

        $noSpk = 'SPK-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $spk = SalesOrder::create([
            'no_spk'              => $noSpk,
            'customer_id'         => $quotation->customer_id,
            'sales_quotation_id'  => $quotation->id, //  tracking asal
            'tanggal'             => now(),
            'total_price'         => $quotation->total_price,
            'notes'               => 'Generated from Quotation: ' . $quotation->no_quotation,
            'status'              => 'pending',
            'created_by'          => Auth::id() ?? 1,
        ]);

        foreach ($quotation->items as $item) {
            $spk->items()->create([
                'product_id'   => $item->product_id,
                'qty_pesanan'  => $item->qty, //  FIXED
                'price'        => $item->price,
                'subtotal'     => $item->subtotal,
            ]);
        }

        //  Optional: ubah jadi converted agar lebih jelas
        $quotation->update([
            'status' => 'approved'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penawaran berhasil dikonversi menjadi SPK.',
            'data' => $spk->load(['customer', 'items.product'])
        ], 201);
    });
}

     /**
    * Update Status aja
    */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
        'status' => 'required|in:draft,sent,approved,rejected',
    ]);

    $quotation = SalesQuotation::find($id);

    if (!$quotation) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // Logic Tambahan: Jika sudah jadi SPK (Accepted), tidak bisa balik ke Draft
    if ($quotation->status === 'approved' && $request->status === 'draft') {
        return response()->json([
            'success' => false, 
            'message' => 'Dokumen yang sudah disetujui tidak bisa dikembalikan ke Draft.'
        ], 422);
    }

    $quotation->update(['status' => $request->status]);

    return response()->json([
        'success' => true,
        'message' => 'Status penawaran berhasil diubah menjadi ' . strtoupper($request->status),
        'current_status' => $quotation->status
    ]);
    }

}
