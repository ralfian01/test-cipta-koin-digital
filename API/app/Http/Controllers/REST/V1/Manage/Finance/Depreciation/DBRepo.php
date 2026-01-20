<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Depreciation;

use App\Http\Libraries\BaseDBRepo;
use App\Models\DepreciationSchedule;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function runMonthlyDepreciation()
    {
        try {
            $businessId = $this->payload['business_id'];
            $periodDate = $this->payload['period_date'];

            // 1. Ambil semua jadwal yang PENDING hingga tanggal periode
            $schedulesToPost = DepreciationSchedule::query()
                ->where('status', 'PENDING')
                ->where('depreciation_date', '<=', $periodDate)
                ->whereHas('fixedAsset', fn($q) => $q->where('business_id', $businessId))
                ->with('fixedAsset.depreciationSetting')
                ->get();

            if ($schedulesToPost->isEmpty()) {
                return (object)['status' => true, 'data' => (object)['journals_created' => 0, 'total_depreciation' => 0]];
            }

            // 2. Agregasi (kelompokkan) jadwal berdasarkan pasangan akun Debit/Kredit
            $journalsToCreate = [];
            foreach ($schedulesToPost as $schedule) {
                $setting = $schedule->fixedAsset->depreciationSetting;
                // Buat kunci unik berdasarkan ID akun beban dan ID akun akumulasi
                $key = $setting->expense_account_id . '-' . $setting->accumulated_depreciation_account_id;

                if (!isset($journalsToCreate[$key])) {
                    $journalsToCreate[$key] = [
                        'expense_account_id' => $setting->expense_account_id,
                        'accumulated_depreciation_account_id' => $setting->accumulated_depreciation_account_id,
                        'total_amount' => 0,
                        'schedule_ids' => [],
                    ];
                }
                $journalsToCreate[$key]['total_amount'] += $schedule->depreciation_amount;
                $journalsToCreate[$key]['schedule_ids'][] = $schedule->id;
            }

            // 3. Buat Jurnal dan Update Status dalam satu transaksi
            DB::transaction(function () use ($journalsToCreate, $businessId, $periodDate) {
                foreach ($journalsToCreate as $data) {
                    $journalEntry = JournalEntry::create([
                        'business_id' => $businessId,
                        'entry_date' => $periodDate,
                        'description' => 'Beban Penyusutan Aset Tetap - ' . Carbon::parse($periodDate)->format('F Y'),
                        'created_by_account_id' => $this->auth['account_id'],
                    ]);

                    $journalEntry->details()->createMany([
                        ['account_chart_id' => $data['expense_account_id'], 'entry_type' => 'DEBIT', 'amount' => $data['total_amount']],
                        ['account_chart_id' => $data['accumulated_depreciation_account_id'], 'entry_type' => 'CREDIT', 'amount' => $data['total_amount']],
                    ]);

                    // Update status semua jadwal yang terlibat dalam jurnal ini
                    DepreciationSchedule::whereIn('id', $data['schedule_ids'])->update([
                        'status' => 'POSTED',
                        'posted_journal_entry_id' => $journalEntry->id,
                    ]);
                }
            });

            $totalDepreciatedAmount = collect($journalsToCreate)->sum('total_amount');

            return (object)[
                'status' => true,
                'data' => (object)[
                    'journals_created' => count($journalsToCreate),
                    'schedules_posted' => $schedulesToPost->count(),
                    'total_depreciation' => $totalDepreciatedAmount
                ]
            ];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
