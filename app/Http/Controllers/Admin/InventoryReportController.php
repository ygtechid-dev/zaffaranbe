<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\ProductConsumption;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    /**
     * 1. Stok Yang Ada (Current Stock)
     * GET /api/v1/admin/reports/inventory/current-stock
     */
    public function currentStock(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = ProductStock::with(['product', 'branch'])
            ->select(
                'product_stocks.*',
                'products.name as product_name',
                'products.sku',
                'products.code',
                'products.cost_price',
                'products.retail_price',
                'products.reorder_point',
                'products.reorder_amount',
                'branches.name as location'
            )
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->join('branches', 'branches.id', '=', 'product_stocks.branch_id');

        if ($branchId && $branchId !== 'all') {
            $query->where('product_stocks.branch_id', $branchId);
        }

        $stocks = $query->orderBy('products.name')->get();

        $data = $stocks->map(function ($stock, $index) {
            $stok = $stock->quantity;
            $stockCost = $stock->product ? $stock->product->cost_price : 0;
            $retailPrice = $stock->product ? $stock->product->retail_price : 0;
            $totalRetail = $stok * $retailPrice;

            return [
                'id' => $stock->id,
                'nama' => $stock->product_name ?? ($stock->product ? $stock->product->name : 'N/A'),
                'lokasi' => $stock->location ?? 'Gudang Utama',
                'stok' => $stok,
                'stockCost' => (float) $stockCost,
                'average' => (float) ($stock->average_cost ?? $stockCost),
                'totalRetail' => (float) $totalRetail,
                'hargaRetail' => (float) $retailPrice,
                'reorderPoint' => $stock->product ? $stock->product->reorder_point : 20,
                'reorderAmount' => $stock->product ? $stock->product->reorder_amount : 50,
            ];
        });

        $summary = [
            'totalAssetValue' => $data->sum(fn($d) => $d['stok'] * $d['stockCost']),
            'totalRetailValue' => $data->sum('totalRetail'),
            'totalProducts' => $data->count(),
            'totalStock' => $data->sum('stok'),
        ];

        return response()->json([
            'data' => $data->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * 2. Performa Penjualan Produk (Product Sales Performance)
     * GET /api/v1/admin/reports/inventory/product-performance
     */
    public function productPerformance(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        // Get stock data
        $stockQuery = ProductStock::with('product')
            ->select('product_id', DB::raw('SUM(quantity) as total_stock'))
            ->groupBy('product_id');

        if ($branchId && $branchId !== 'all') {
            $stockQuery->where('branch_id', $branchId);
        }

        $stocks = $stockQuery->get()->keyBy('product_id');

        // Get sales data from stock movements (out type)
        $salesQuery = StockMovement::with('product')
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as qty_sold'),
                DB::raw('SUM(quantity * cost_price) as hpp'),
                DB::raw('SUM(quantity * cost_price * 1.6) as total_sales') // Assuming 60% margin
            )
            ->whereIn('movement_type', ['out'])
            ->whereBetween('movement_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('product_id');

        if ($branchId && $branchId !== 'all') {
            $salesQuery->where('branch_id', $branchId);
        }

        $sales = $salesQuery->get()->keyBy('product_id');

        // Get all products
        $products = Product::where('is_active', true)->get();

        $data = $products->map(function ($product, $index) use ($stocks, $sales) {
            $stock = $stocks->get($product->id);
            $sale = $sales->get($product->id);

            $stok = $stock ? $stock->total_stock : 0;
            $qtySold = $sale ? $sale->qty_sold : 0;
            $hpp = $sale ? (float) $sale->hpp : 0;
            $totalSales = $sale ? (float) $sale->total_sales : 0;
            $avgSales = $qtySold > 0 ? $totalSales / $qtySold : 0;

            return [
                'id' => $product->id,
                'nama' => $product->name,
                'stok' => (int) $stok,
                'qtyTerjual' => (int) $qtySold,
                'hpp' => $hpp,
                'penjualanBersih' => $totalSales,
                'rataPenjualan' => $avgSales,
            ];
        })->filter(fn($d) => $d['qtyTerjual'] > 0 || $d['stok'] > 0);

        $summary = [
            'totalPenjualanBersih' => $data->sum('penjualanBersih'),
            'totalHpp' => $data->sum('hpp'),
            'totalQtyTerjual' => $data->sum('qtyTerjual'),
        ];

        return response()->json([
            'data' => $data->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * 3. Log Pergerakan Stok (Stock Movement Log)
     * GET /api/v1/admin/reports/inventory/stock-movement-log
     */
    public function stockMovementLog(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $query = StockMovement::with(['product', 'branch', 'user'])
            ->whereBetween('movement_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderBy('movement_date', 'desc');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $movements = $query->get();

        $data = $movements->map(function ($movement) {
            $adjustmentValue = $movement->movement_type === 'in' || $movement->movement_type === 'return' 
                ? $movement->quantity 
                : -$movement->quantity;

            if ($movement->movement_type === 'adjustment' || $movement->movement_type === 'opname') {
                $adjustmentValue = $movement->quantity_after - $movement->quantity_before;
            }

            return [
                'id' => $movement->id,
                'productName' => $movement->product ? $movement->product->name : 'N/A',
                'lokasi' => $movement->branch ? $movement->branch->name : 'Gudang Utama',
                'kodeBarang' => $movement->product ? $movement->product->code : 'N/A',
                'penyesuaian' => $adjustmentValue,
                'hargaModal' => (float) $movement->cost_price,
                'namaStaff' => $movement->user ? $movement->user->name : 'System',
                'deskripsi' => $movement->description ?? ucfirst(str_replace('_', ' ', $movement->movement_type)),
                'diTangan' => $movement->quantity_after,
                'tanggal' => Carbon::parse($movement->movement_date)->format('d M Y'),
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalMovements' => $data->count(),
                'totalIn' => $movements->whereIn('movement_type', ['in', 'return'])->sum('quantity'),
                'totalOut' => $movements->where('movement_type', 'out')->sum('quantity'),
            ],
        ]);
    }

    /**
     * 4. Kalkulasi Pergerakan Stok (Stock Movement Calculation)
     * GET /api/v1/admin/reports/inventory/stock-calculation
     */
    public function stockCalculation(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $products = Product::with(['supplier'])
            ->where('is_active', true)
            ->get();

        $data = $products->map(function ($product) use ($branchId, $dateFrom, $dateTo) {
            // Get starting stock (first movement before date range or initial stock)
            $startMovement = StockMovement::where('product_id', $product->id)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->where('movement_date', '<', $dateFrom . ' 00:00:00')
                ->orderBy('movement_date', 'desc')
                ->first();

            $startStock = $startMovement ? $startMovement->quantity_after : 0;

            // If no movement before, get current stock and calculate backwards
            if (!$startMovement) {
                $currentStock = ProductStock::where('product_id', $product->id)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->sum('quantity');
                
                // Calculate net movement in period
                $periodIn = StockMovement::where('product_id', $product->id)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('movement_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                    ->whereIn('movement_type', ['in', 'return'])
                    ->sum('quantity');

                $periodOut = StockMovement::where('product_id', $product->id)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('movement_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                    ->where('movement_type', 'out')
                    ->sum('quantity');

                $startStock = $currentStock - $periodIn + $periodOut;
            }

            // Get received quantity in period
            $received = StockMovement::where('product_id', $product->id)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->whereBetween('movement_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->whereIn('movement_type', ['in', 'return'])
                ->sum('quantity');

            return [
                'id' => $product->id,
                'productName' => $product->name,
                'sku' => $product->sku,
                'kodeBarang' => $product->code,
                'brandName' => $product->brand_name ?? 'Naqupos Originals',
                'category' => $product->category ?? 'Umum',
                'pemasok' => $product->supplier ? $product->supplier->name : 'N/A',
                'startStock' => (int) max(0, $startStock),
                'diterima' => (int) $received,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalProducts' => $data->count(),
                'totalStartStock' => $data->sum('startStock'),
                'totalReceived' => $data->sum('diterima'),
            ],
        ]);
    }

    /**
     * 5. Konsumsi Produk (Product Consumption)
     * GET /api/v1/admin/reports/inventory/product-consumption
     */
    public function productConsumption(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $query = ProductConsumption::with(['product'])
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('AVG(cost_price) as avg_cost'),
                DB::raw('SUM(quantity * cost_price) as total_cost')
            )
            ->whereBetween('consumption_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('product_id');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $consumptions = $query->get();

        $data = $consumptions->map(function ($consumption, $index) {
            $product = Product::find($consumption->product_id);

            return [
                'id' => $consumption->product_id,
                'nama' => $product ? $product->name : 'N/A',
                'qty' => (int) $consumption->total_qty,
                'sku' => $product ? $product->sku : 'N/A',
                'kodeBarang' => $product ? $product->code : 'N/A',
                'avgCostPrice' => (float) $consumption->avg_cost,
                'totalBiaya' => (float) $consumption->total_cost,
            ];
        });

        $summary = [
            'totalItems' => $data->count(),
            'totalQty' => $data->sum('qty'),
            'totalBiaya' => $data->sum('totalBiaya'),
        ];

        return response()->json([
            'data' => $data->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * 5b. Detail Konsumsi Produk (Product Consumption Detail)
     * GET /api/v1/admin/reports/inventory/product-consumption/{productId}
     */
    public function productConsumptionDetail(Request $request, $productId)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $query = ProductConsumption::with(['user', 'booking', 'branch'])
            ->where('product_id', $productId)
            ->whereBetween('consumption_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderBy('consumption_date', 'desc');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $consumptions = $query->get();

        $data = $consumptions->map(function ($consumption) {
            return [
                'id' => $consumption->id,
                'tanggal' => Carbon::parse($consumption->consumption_date)->format('d M Y H:i'),
                'qty' => $consumption->quantity,
                'costPrice' => (float) $consumption->cost_price,
                'totalBiaya' => (float) ($consumption->quantity * $consumption->cost_price),
                'staff' => $consumption->user ? $consumption->user->name : 'N/A',
                'lokasi' => $consumption->branch ? $consumption->branch->name : 'N/A',
                'booking' => $consumption->booking ? $consumption->booking->booking_ref : null,
                'notes' => $consumption->notes,
            ];
        });

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'code' => $product->code,
            ],
            'data' => $data->values(),
            'summary' => [
                'totalQty' => $data->sum('qty'),
                'totalBiaya' => $data->sum('totalBiaya'),
            ],
        ]);
    }
}
