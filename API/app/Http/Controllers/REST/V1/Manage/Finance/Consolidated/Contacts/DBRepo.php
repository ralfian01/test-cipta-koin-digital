<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\Contacts;

use App\Http\Libraries\BaseDBRepo;
use App\Models\FinanceContact;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getConsolidatedData()
    {
        try {
            // 1. Ambil SEMUA kontak yang relevan, beserta nama bisnisnya
            $query = FinanceContact::with('business:id,name')->orderBy('name', 'asc');

            // Terapkan filter jika ada
            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
            }
            if (isset($this->payload['contact_type'])) {
                $query->where('contact_type', $this->payload['contact_type']);
            }

            $allContacts = $query->get();

            // 2. Kelompokkan semua kontak berdasarkan 'name' (case-insensitive)
            $groupedByName = $allContacts->groupBy(function ($item) {
                return strtolower($item['name']); // Mengelompokkan "PT ABC" dan "pt abc"
            });

            $consolidatedList = [];

            // 3. Proses setiap kelompok nama kontak
            foreach ($groupedByName as $nameGroup) {
                $firstContact = $nameGroup->first();
                $roles = $nameGroup->pluck('contact_type')->unique();

                // 4. Bangun array asosiasi (di bisnis mana saja kontak ini ada)
                $associations = $nameGroup->map(function ($contact) {
                    return [
                        'contact_id_in_business' => $contact->id,
                        'business_id' => $contact->business_id,
                        'business_name' => $contact->business->name,
                        'contact_type' => $contact->contact_type,
                    ];
                });

                // 5. Buat entri konsolidasi
                $consolidatedList[] = [
                    'contact_name' => $firstContact->name, // Ambil nama asli dari data pertama
                    'is_multi_business' => $nameGroup->count() > 1,
                    'is_dual_role' => $roles->count() > 1, // Jika ada 'CUSTOMER' dan 'VENDOR'
                    'associations' => $associations->values()->toArray(),
                ];
            }

            return (object)['status' => true, 'data' => $consolidatedList];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
