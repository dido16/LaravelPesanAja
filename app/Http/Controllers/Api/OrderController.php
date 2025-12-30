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
    /**
     * FUNGSI 1: BUAT PESANAN BARU (STORE)
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'meja'          => 'required',
            'customer_name' => 'required|string',
            'device_id'     => 'required|string',
            'items'         => 'required|array',
            'items.*.menu_id'  => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.level_id' => 'nullable|exists:levels,id',
            'items.*.notes'    => 'nullable|string',
        ]);

        // 2. Cek Keberadaan Meja
        $nomorMeja = str_pad($request->meja, 2, '0', STR_PAD_LEFT);
        $table = Table::where('table_number', $nomorMeja)->first();

        if (!$table) {
            return response()->json([
                'success' => false, 
                'message' => 'Meja ' . $nomorMeja . ' tidak ditemukan dalam database.'
            ], 404);
        }

        // --- [MODIFIKASI PENTING: REPEAT ORDER] ---
        // Pengecekan status meja SAYA MATIKAN (Comment) agar user bisa Repeat Order.
        // Jika kode ini aktif, user akan kena Error 400 saat pesan kedua kalinya.
        
        /* if ($table->status != 'available') {
            return response()->json([
                'success' => false, 
                'message' => 'Meja ini sedang digunakan! Selesaikan pembayaran dulu.'
            ], 400);
        }
        */
        // ------------------------------------------

        DB::beginTransaction();
        try {
            $runningSubtotal = 0;

            // 3. Buat Header Pesanan (Order)
            $order = Order::create([
                'table_id'      => $table->id,
                'device_id'     => $request->device_id,
                'customer_name' => $request->customer_name,
                'subtotal'      => 0, // Dihitung nanti
                'tax_amount'    => 0,
                'final_total'   => 0,
                'status'        => 'pending'
            ]);

            // 4. Buat Rincian Pesanan (Order Items)
            foreach ($request->items as $item) {
                $menu = Menu::find($item['menu_id']);
                
                // Cek apakah ada level yang dipilih
                $level = isset($item['level_id']) ? Level::find($item['level_id']) : null;

                if ($menu) {
                    // Hitung Harga Satuan (Harga Menu + Harga Level)
                    $extraCost = $level ? $level->extra_cost : 0;
                    $unitPrice = $menu->price + $extraCost;
                    
                    // Hitung Total per Baris
                    $lineTotal = $unitPrice * $item['quantity'];
                    $runningSubtotal += $lineTotal;

                    // Simpan ke OrderItem
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

            // 5. Update Total & Pajak di Header Order
            $tax = $runningSubtotal * 0.1; // PPN 10%
            $finalTotal = $runningSubtotal + $tax;

            $order->update([
                'subtotal'    => $runningSubtotal,
                'tax_amount'  => $tax,
                'final_total' => $finalTotal
            ]);

            // 6. Update Status Meja
            $table->update(['status' => 'occupied']);

            DB::commit();

            // 7. Return Response Sukses
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dikirim ke dapur!',
                // PERBAIKAN: Menggunakan key 'data' agar konsisten dengan Android
                'data'    => $order->load('orderItems.menu', 'orderItems.level') 
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error Store Order: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * FUNGSI 2: BAYAR PESANAN / KONFIRMASI (MARK AS PAID)
     */
    public function markAsPaid($id)
    {
        try {
            $order = Order::find($id);
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
            }

            // Hanya proses jika statusnya masih pending
            if ($order->status == 'pending') {
                $order->update(['status' => 'processing']);

                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran diterima! Pesanan sedang diproses dapur.',
                    'data' => $order
                ]);
            }

            return response()->json([
                'success' => false, 
                'message' => 'Pesanan ini sudah dibayar atau dibatalkan.'
            ], 400);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * FUNGSI 3: RIWAYAT PESANAN (HISTORY)
     */
    public function history(Request $request)
    {
        $deviceId = $request->query('device_id');

        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID wajib dikirim'
            ], 400);
        }

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

    /**
     * FUNGSI 4: UPDATE STATUS PESANAN (UNTUK ADMIN/KASIR)
     */
    public function updateStatus(Request $request, $id)
    {
        Log::info('Update Status Request', ['id' => $id, 'status' => $request->status]);

        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order Not Found'], 404);

        $newStatus = $request->status;
        $order->update(['status' => $newStatus]);

        // Jika dicancel atau completed, cek apakah meja bisa dikosongkan
        if ($newStatus == 'cancelled' || $newStatus == 'completed') {
            if ($order->table_id) {
                // Cek order lain yang masih aktif di meja ini
                $activeOrders = Order::where('table_id', $order->table_id)
                                     ->whereIn('status', ['pending', 'processing'])
                                     ->where('id', '!=', $id)
                                     ->exists();
                
                if (!$activeOrders) {
                    Table::where('id', $order->table_id)->update(['status' => 'available']);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Status berhasil diubah']);
    }
}