<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items\Batches;

use App\Http\Libraries\BaseDBRepo;
use App\Models\InventoryItem;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil daftar batch stok untuk satu item inventaris spesifik.
     * @return object
     */
    public function getBatchesByItem()
    {
        try {
            $item = InventoryItem::findOrFail($this->payload['id']);
            $perPage = $this->payload['per_page'] ?? 15;

            // Ambil semua batch yang terhubung ke item ini
            $batches = $item->batches()
                ->orderBy('received_date', 'asc') // Urutkan sesuai FIFO
                ->paginate($perPage);

            return (object)['status' => true, 'data' => $batches->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
