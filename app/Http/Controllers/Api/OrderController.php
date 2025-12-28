<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Menu;
use App\Models\Table;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    // --- FUNGSI 1: BUAT PESANAN (STORE) ---
    public function store(Request $request)
    {
        $request->validate([
            'meja'          => 'required',
            'customer_name' => 'required|string',
            'device_id'     => 'required|string', // <--- TAMBAHAN: Validasi Device ID
            'items'         => 'required|array',
            'items.*.menu_id'  => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.level_id' => 'nullable|exists:levels,id',
            'items.*.notes'    => 'nullable|string',
        ]);

        $nomorMeja = str_pad($request->meja, 2, '0', STR_PAD_LEFT);
        $table = Table::where('table_number', $nomorMeja)->first();

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Meja ' . $nomorMeja . ' tidak ditemukan'], 404);
        }

        if ($table->status != 'available') {
            return response()->json(['success' => false, 'message' => 'Meja ini sedang digunakan! Selesaikan pembayaran dulu.'], 400);
        }

        DB::beginTransaction();
        try {
            $runningSubtotal = 0;

            // 1. Buat Order Header
            $order = Order::create([
                'table_id'      => $table->id,
                'device_id'     => $request->device_id, // <--- TAMBAHAN: Simpan Device ID ke Database
                'customer_name' => $request->customer_name,
                'subtotal'      => 0,
                'tax_amount'    => 0,
                'final_total'   => 0,
                'status'        => 'pending'
            ]);

            // 2. Buat Order Items
            foreach ($request->items as $item) {
                $menu = Menu::find($item['menu_id']);
                $level = isset($item['level_id']) ? Level::find($item['level_id']) : null;

                if ($menu) {
                    $extraCost = $level ? $level->extra_cost : 0;
                    $unitPrice = $menu->price + $extraCost;
                    $lineTotal = $unitPrice * $item['quantity'];
                    $runningSubtotal += $lineTotal;

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'menu_id'    => $menu->id,
                        'level_id'   => $item['level_id'] ?? null,
                        'quantity'   => $item['quantity'],
                        'unit_price' => $unitPrice,
                        'notes'      => $item['notes'] ?? null
                    ]);
                }
            }

            // 3. Update Total & Pajak
            $tax = $runningSubtotal * 0.1;
            $order->update([
                'subtotal'    => $runningSubtotal,
                'tax_amount'  => $tax,
                'final_total' => $runningSubtotal + $tax
            ]);

            // 4. Update Status Meja
            $table->update(['status' => 'occupied']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dikirim ke dapur!',
                'data'    => $order->load('orderItems.menu', 'orderItems.level')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    // --- FUNGSI 2: BAYAR PESANAN (markAsPaid) ---
    public function markAsPaid($id)
    {
        try {
            $order = Order::find($id);
            if (!$order) return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);

            if ($order->status == 'pending') {
                $order->update(['status' => 'processing']);

                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran sedang diproses! Pesanan akan segera dimasak.',
                    'data' => $order
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Status order tidak valid untuk dibayar']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // --- FUNGSI 3: RIWAYAT PESANAN (BARU) ---
    public function history(Request $request)
    {
        // Ambil device_id dari parameter URL (misal: ?device_id=xxxx)
        $deviceId = $request->query('device_id');

        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID wajib dikirim'
            ], 400);
        }

        // Cari order berdasarkan device_id, urutkan dari yang terbaru
        // Kita load juga relasi orderItems dan menu biar datanya lengkap
        $orders = Order::where('device_id', $deviceId)
            ->with('orderItems.menu', 'orderItems.level')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat berhasil diambil',
            'data'    => $orders
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        // 🎯 Tambahkan ini untuk cek di storage/logs/laravel.log
        Log::info('Permintaan Update Status Masuk', [
            'id_order' => $id,
            'status_baru' => $request->status
        ]);

        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Not Found'], 404);

        $newStatus = $request->status;

        // Gunakan DB Table jika model 'table' bermasalah
        if ($newStatus == 'cancelled') {
            $order->update(['status' => 'cancelled']);
            if ($order->table_id) {
                DB::table('tables')->where('id', $order->table_id)->update(['status' => 'available']);
            }
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }
}
