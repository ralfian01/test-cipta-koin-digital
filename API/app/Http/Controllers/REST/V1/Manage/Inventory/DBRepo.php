<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory;

use App\Http\Libraries\BaseDBRepo;
use App\Models\InventoryBatch;
use App\Models\InventoryLedger;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Memeriksa apakah total stok untuk sebuah item mencukupi.
     * Method ini dipanggil dari nextValidation() di controller API.
     * @param int $itemId
     * @param int $quantityToIssue
     * @return bool
     */
    public static function isStockSufficient(int $itemId, int $quantityToIssue): bool
    {
        // Lakukan query read-only untuk menghitung total stok yang tersedia.
        $totalStock = InventoryBatch::where('item_id', $itemId)->sum('current_quantity');

        // Kembalikan true jika stok lebih besar atau sama dengan yang diminta.
        return $totalStock >= $quantityToIssue;
    }

    public function stockIn()
    {
        try {
            return DB::transaction(function () {
                $batch = InventoryBatch::create([
                    'item_id' => $this->payload['item_id'],
                    'quantity_received' => $this->payload['quantity_received'],
                    'current_quantity' => $this->payload['quantity_received'],
                    'unit_cost' => $this->payload['unit_cost'],
                    'received_date' => $this->payload['received_date'],
                    'expiration_date' => $this->payload['expiration_date'] ?? null,
                ]);
                $batch->ledgers()->create([
                    'item_id' => $batch->item_id,
                    'movement_type' => 'STOCK_IN',
                    'quantity' => $batch->quantity_received,
                    'notes' => $batch->notes ?? null
                ]);
                return (object)['status' => true, 'data' => $batch];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function stockOut()
    {
        try {
            return DB::transaction(function () {
                $itemId = $this->payload['item_id'];
                $quantityToIssue = $this->payload['quantity'];

                $batches = InventoryBatch::where('item_id', $itemId)
                    ->where('current_quantity', '>', 0)
                    ->orderBy('received_date', 'asc') // Kunci FIFO
                    ->lockForUpdate() // Tetap penting untuk mencegah race condition
                    ->get();

                foreach ($batches as $batch) {
                    if ($quantityToIssue <= 0) break;

                    $take = min($quantityToIssue, $batch->current_quantity);

                    $batch->decrement('current_quantity', $take);

                    $batch->ledgers()->create([
                        'item_id' => $itemId,
                        'movement_type' => 'STOCK_OUT',
                        'quantity' => -$take,
                        'issued_to' => $this->payload['issued_to'] ?? null,
                        'notes' => $this->payload['notes'] ?? null,
                    ]);

                    $quantityToIssue -= $take;
                }

                // Jika karena race condition stok ternyata tidak cukup, transaction akan rollback.
                if ($quantityToIssue > 0) {
                    throw new Exception("Concurrency error: Stock became insufficient during transaction.");
                }

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mengambil riwayat pergerakan stok (ledger) dengan filter.
     * @return object
     */
    public function getLedgers()
    {
        try {
            $query = InventoryLedger::query()
                // Eager load relasi untuk menampilkan detail yang relevan
                ->with([
                    'item' => fn($q) => $q->select('id', 'name', 'sku'),
                    'batch' => fn($q) => $q->select('id', 'received_date', 'unit_cost'),
                ]);

            // Terapkan filter
            if (isset($this->payload['item_id'])) {
                $query->where('item_id', $this->payload['item_id']);
            }
            if (isset($this->payload['batch_id'])) {
                $query->where('batch_id', $this->payload['batch_id']);
            }
            if (isset($this->payload['movement_type'])) {
                $query->where('movement_type', $this->payload['movement_type']);
            }
            if (isset($this->payload['start_date'])) {
                $query->whereDate('movement_date', '>=', $this->payload['start_date']);
            }
            if (isset($this->payload['end_date'])) {
                $query->whereDate('movement_date', '<=', $this->payload['end_date']);
            }

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('movement_date', 'desc')->paginate($perPage);

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
