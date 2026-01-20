<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Contacts;

use App\Http\Libraries\BaseDBRepo;
use App\Models\FinanceContact;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = FinanceContact::query();

            // Kasus Detail
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            // Kasus Daftar (dengan filter)
            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }
            if (isset($this->payload['contact_type'])) {
                $query->where('contact_type', $this->payload['contact_type']);
            }
            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
            }

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('name', 'asc')->paginate($perPage);

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            $contact = FinanceContact::create($this->payload);
            return (object)['status' => true, 'data' => (object)['id' => $contact->id]];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            $contact = FinanceContact::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id', 'business_id', 'contact_type']); // Mencegah perubahan business & type
            $contact->update($dbPayload);
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $contact = FinanceContact::findOrFail($this->payload['id']);
            // onDelete('restrict') di invoices/bills akan mencegah penghapusan jika kontak sudah bertransaksi
            $contact->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            // Tangkap error foreign key constraint
            if (str_contains($e->getMessage(), 'violates foreign key constraint')) {
                return (object)['status' => false, 'message' => 'Cannot delete contact: It has been used in transactions (invoices or bills).'];
            }
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
