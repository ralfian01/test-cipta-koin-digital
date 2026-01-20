<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports\Consolidated;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountChart;
use App\Models\CooperationMemberSavingsTransaction;
use App\Models\CooperationSavingsType;
use App\Models\Member;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Menghasilkan Laporan Laba Rugi (PHU) Konsolidasi.
     */
    public function generateConsolidatedIncomeStatement()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $revenues = $this->calculateGroupTotal('REVENUE', $startDate, $endDate);
            $totalRevenue = $revenues->sum('total');
            $expenses = $this->calculateGroupTotal('EXPENSE', $startDate, $endDate);
            $totalExpense = $expenses->sum('total');

            $report = [
                'report_name' => 'Laporan Perhitungan Hasil Usaha (Konsolidasi)',
                'period' => "{$startDate} to {$endDate}",
                'revenues' => ['accounts' => $revenues->toArray(), 'total' => $totalRevenue],
                'expenses' => ['accounts' => $expenses->toArray(), 'total' => $totalExpense],
                'net_profit' => $totalRevenue - $totalExpense,
            ];
            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    /**
     * Menghasilkan Laporan Neraca Konsolidasi.
     */
    public function generateConsolidatedBalanceSheet()
    {
        try {
            $endDate = Carbon::parse($this->payload['end_date']);
            $startDate = isset($this->payload['start_date'])
                ? Carbon::parse($this->payload['start_date'])
                : $endDate->copy()->startOfYear();

            // Panggil method-method yang sudah diperbaiki
            $assets = $this->calculateAccountGroupBalance('ASSET', $endDate->toDateString());
            $totalAssets = $assets->sum('balance');

            $liabilities = $this->calculateAccountGroupBalance('LIABILITY', $endDate->toDateString());
            $totalLiabilities = $liabilities->sum('balance');

            $equityAccounts = $this->calculateAccountGroupBalance('EQUITY', $endDate->toDateString());
            $totalEquityAccounts = $equityAccounts->sum('balance');

            $profitForPeriod = $this->calculateProfitForPeriod($startDate->toDateString(), $endDate->toDateString());
            $totalEquity = $totalEquityAccounts + $profitForPeriod;

            $report = [
                'report_name' => 'Laporan Posisi Keuangan (Neraca)',
                'as_of_date' => $endDate->toDateString(),
                'assets' => ['accounts' => $assets->toArray(), 'total' => $totalAssets],
                'liabilities' => ['accounts' => $liabilities->toArray(), 'total' => $totalLiabilities],
                'equity' => [
                    'accounts' => $equityAccounts->toArray(),
                    'retained_earnings' => [
                        'name' => 'Sisa Hasil Usaha (SHU) Periode Berjalan',
                        'amount' => $profitForPeriod,
                    ],
                    'total' => $totalEquity,
                ],
                'check_balance' => round($totalAssets - ($totalLiabilities + $totalEquity), 2),
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menghasilkan Laporan Rasio Keuangan.
     */
    public function generateConsolidatedRatios()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // 1. Ambil variabel dari Laporan Neraca (per end_date)
            $balanceSheetData = $this->generateConsolidatedBalanceSheet()->data;
            $totalAssets = $balanceSheetData['assets']['total'];
            $totalLiabilities = $balanceSheetData['liabilities']['total'];
            $totalEquity = $balanceSheetData['equity']['total'];

            // 2. Ambil variabel dari Laporan Laba Rugi (untuk periode start_date -> end_date)
            $incomeStatementData = $this->generateConsolidatedIncomeStatement()->data;
            $profit = $incomeStatementData['net_profit'];

            // 3. Hitung Rasio
            // Rasio A: Kemampuan membayar hutang
            $debtRatioValue = ($totalLiabilities > 0) ? ($totalAssets / $totalLiabilities) * 100 : 0;

            // Rasio B: Rentabilitas (Return on Equity)
            $profitabilityRatioValue = ($totalEquity > 0) ? ($profit / $totalEquity) * 100 : 0;

            // 4. Susun data laporan
            $report = [
                'report_name' => 'Laporan Rasio Keuangan',
                'period' => "{$startDate} to {$endDate}",
                'debt_ratio' => [
                    'name' => 'Rasio Hutang Terhadap Aset',
                    'formula' => '(Seluruh Aktiva / Seluruh Hutang) * 100%',
                    'value' => round($debtRatioValue, 2),
                    'interpretation' => sprintf(
                        'Setiap Rp 1,00 hutang dijamin oleh aset sebesar Rp %.2f.',
                        $debtRatioValue / 100
                    ),
                    'components' => [
                        'total_assets' => $totalAssets,
                        'total_liabilities' => $totalLiabilities,
                    ],
                ],
                'profitability_ratio' => [
                    'name' => 'Rentabilitas Modal Sendiri (Return on Equity)',
                    'formula' => '(Laba / Modal) * 100%',
                    'value' => round($profitabilityRatioValue, 2),
                    'interpretation' => sprintf(
                        'Setiap Rp 1,00 modal menghasilkan laba sebesar Rp %.2f.',
                        $profitabilityRatioValue / 100
                    ),
                    'components' => [
                        'profit' => $profit,
                        'equity' => $totalEquity,
                    ],
                ],
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menghasilkan Laporan Arus Kas Konsolidasi.
     */
    public function generateConsolidatedCashFlowStatement()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $cashAccountIds = AccountChart::where('account_type', 'ASSET')
                ->where(function ($query) {
                    $parent = AccountChart::where('account_name', 'Kas dan Setara Kas')->first();
                    if ($parent) {
                        $query->where('parent_id', $parent->id)->orWhere('id', $parent->id);
                    } else {
                        $query->where('account_name', 'like', '%Kas%');
                    }
                })
                ->whereDoesntHave('children')->pluck('id');

            if ($cashAccountIds->isEmpty()) throw new Exception("No 'Cash and Cash Equivalents' account found.");

            $beginningBalance = $this->getCashBalanceAsOf($cashAccountIds, Carbon::parse($startDate)->subDay()->toDateString());
            $endingBalance = $this->getCashBalanceAsOf($cashAccountIds, $endDate);

            $operating = $this->getCashFlowForActivity('OPERATING', $cashAccountIds, $startDate, $endDate);
            $investing = $this->getCashFlowForActivity('INVESTING', $cashAccountIds, $startDate, $endDate);
            $financing = $this->getCashFlowForActivity('FINANCING', $cashAccountIds, $startDate, $endDate);

            $netCashFlow = $operating['total'] + $investing['total'] + $financing['total'];

            $report = [
                'report_name' => 'Laporan Arus Kas (Konsolidasi)',
                'period' => "{$startDate} to {$endDate}",
                'beginning_cash_balance' => $beginningBalance,
                'operating_activities' => $operating,
                'investing_activities' => $investing,
                'financing_activities' => $financing,
                'net_cash_flow' => $netCashFlow,
                'ending_cash_balance' => $endingBalance,
                'check_balance' => round($beginningBalance + $netCashFlow - $endingBalance, 2)
            ];
            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    private function getCashBalanceAsOf($cashAccountIds, string $endDate): float
    {
        $prefix = DB::getTablePrefix();

        $balance = DB::table("journal_entry_details")
            ->join("journal_entries", "journal_entry_details.journal_entry_id", '=', "journal_entries.id")
            ->whereIn("journal_entry_details.account_chart_id", $cashAccountIds)
            ->where("journal_entries.entry_date", '<=', $endDate)
            ->selectRaw("SUM(CASE WHEN {$prefix}journal_entry_details.entry_type = 'DEBIT' THEN {$prefix}journal_entry_details.amount ELSE -{$prefix}journal_entry_details.amount END) as total_balance")
            ->first();

        return (float) ($balance->total_balance ?? 0);
    }

    private function getCashFlowForActivity(string $activityType, $cashAccountIds, string $startDate, string $endDate): array
    {
        $cashJournalEntryIds = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $cashAccountIds)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->pluck('journal_entry_details.journal_entry_id')->unique();

        if ($cashJournalEntryIds->isEmpty()) return ['accounts' => [], 'total' => 0];

        $prefix = DB::getTablePrefix();

        $flows = DB::table("journal_entry_details")
            ->join("account_charts", "journal_entry_details.account_chart_id", '=', "account_charts.id")
            ->whereIn("journal_entry_details.journal_entry_id", $cashJournalEntryIds)
            ->where("account_charts.cash_flow_activity", $activityType)
            ->select(
                "account_charts.account_name",
                DB::raw("SUM(CASE WHEN {$prefix}journal_entry_details.entry_type = 'CREDIT' THEN {$prefix}journal_entry_details.amount ELSE -{$prefix}journal_entry_details.amount END) as total_flow")
            )
            ->groupBy("account_charts.account_name")
            ->get();

        return ['accounts' => $flows->toArray(), 'total' => (float) $flows->sum('total_flow')];
    }

    /**
     * Menghasilkan Laporan Perubahan Modal Konsolidasi.
     */
    public function generateConsolidatedEquityChangeStatement()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // Hitung saldo awal ekuitas pada hari sebelum periode dimulai
            $beginningEquity = $this->calculateAccountGroupBalance('EQUITY', Carbon::parse($startDate)->subDay()->toDateString())->sum('balance');

            // Hitung laba/rugi selama periode berjalan
            $profitForPeriod = $this->calculateProfitForPeriod($startDate, $endDate);

            // TODO: Implementasikan logika untuk menghitung setoran dan penarikan modal
            // Ini memerlukan penandaan akun spesifik di CoA (misal: 'Simpanan Pokok', 'Prive')
            $capitalInjections = 0;
            $capitalWithdrawals = 0;

            $endingEquity = $beginningEquity + $profitForPeriod + $capitalInjections - $capitalWithdrawals;

            $report = [
                'report_name' => 'Laporan Perubahan Modal (Konsolidasi)',
                'period' => "{$startDate} to {$endDate}",
                'beginning_equity' => $beginningEquity,
                'profit_for_period' => $profitForPeriod,
                'capital_injections' => $capitalInjections,
                'capital_withdrawals' => $capitalWithdrawals,
                'ending_equity' => $endingEquity,
            ];
            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    /*
     * =================================================================================
     * METHOD PENDUKUNG (TANPA FILTER business_id)
     * =================================================================================
     */

    // private function calculateProfitForPeriod(string $startDate, string $endDate): float
    // {
    //     $revenueTotal = $this->getGroupTotalForPeriod('REVENUE', $startDate, $endDate);
    //     $expenseTotal = $this->getGroupTotalForPeriod('EXPENSE', $startDate, $endDate);
    //     return (float) ($revenueTotal - $expenseTotal);
    // }

    // /**
    //  * Helper untuk menghitung total pergerakan untuk grup akun (Laba/Rugi).
    //  * (Versi Final yang Robust)
    //  */
    // private function getGroupTotalForPeriod(string $accountType, string $startDate, string $endDate): float
    // {
    //     $accountIds = AccountChart::where('account_type', $accountType)->whereDoesntHave('children')->pluck('id');
    //     if ($accountIds->isEmpty()) return 0;

    //     $totalDebits = DB::table('journal_entry_details')
    //         ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
    //         ->whereIn('journal_entry_details.account_chart_id', $accountIds)
    //         ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
    //         ->where('journal_entry_details.entry_type', 'DEBIT')
    //         ->sum('journal_entry_details.amount');

    //     $totalCredits = DB::table('journal_entry_details')
    //         ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
    //         ->whereIn('journal_entry_details.account_chart_id', $accountIds)
    //         ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
    //         ->where('journal_entry_details.entry_type', 'CREDIT')
    //         ->sum('journal_entry_details.amount');

    //     // REVENUE (normal CREDIT) = credits - debits
    //     // EXPENSE (normal DEBIT) = debits - credits
    //     return $accountType === 'REVENUE' ? ($totalCredits - $totalDebits) : ($totalDebits - $totalCredits);
    // }

    // /**
    //  * Menghitung saldo akhir untuk sebuah grup akun (Neraca).
    //  * (Versi Final yang Robust)
    //  */
    // private function calculateAccountGroupBalance(string $accountType, string $endDate)
    // {
    //     $accounts = AccountChart::query()
    //         ->where('account_type', $accountType)->whereDoesntHave('children')->get();

    //     foreach ($accounts as $account) {
    //         $totalDebits = DB::table('journal_entry_details')
    //             ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
    //             ->where('journal_entry_details.account_chart_id', $account->id)
    //             ->where('journal_entries.entry_date', '<=', $endDate)
    //             ->where('journal_entry_details.entry_type', 'DEBIT')
    //             ->sum('journal_entry_details.amount');

    //         $totalCredits = DB::table('journal_entry_details')
    //             ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
    //             ->where('journal_entry_details.account_chart_id', $account->id)
    //             ->where('journal_entries.entry_date', '<=', $endDate)
    //             ->where('journal_entry_details.entry_type', 'CREDIT')
    //             ->sum('journal_entry_details.amount');

    //         if ($account->normal_balance === 'DEBIT') {
    //             $balance = $totalDebits - $totalCredits;
    //         } else {
    //             $balance = $totalCredits - $totalDebits;
    //         }
    //         $account->balance = (float) $balance;
    //     }

    //     return $accounts->map(fn($account) => [
    //         'account_code' => $account->account_code,
    //         'account_name' => $account->account_name,
    //         'balance' => $account->balance,
    //     ]);
    // }

    // private function calculateGroupTotal(string $accountType, string $startDate, string $endDate)
    // {
    //     // Metode ini menggunakan withSum, yang lebih aman dan tidak memerlukan DB::raw
    //     return AccountChart::query()
    //         ->where('account_type', $accountType)
    //         ->whereDoesntHave('children')
    //         ->withSum(['journalEntryDetails' => function ($query) use ($startDate, $endDate) {
    //             $query->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
    //                 $q->whereBetween('entry_date', [$startDate, $endDate]);
    //             });
    //         }], 'amount')
    //         ->get()
    //         ->map(fn($account) => [
    //             'account_code' => $account->account_code,
    //             'account_name' => $account->account_name,
    //             'total' => (float) $account->journal_entry_details_sum_amount ?? 0,
    //         ]);
    // }




    // ---
    /**
     * Menghitung total pergerakan untuk grup akun (Laba/Rugi) secara KONSOLIDASI.
     */
    private function calculateGroupTotal(string $accountType, string $startDate, string $endDate)
    {
        // --- PERBAIKAN KRUSIAL DI SINI ---
        // Kita tidak lagi mengambil model AccountChart, tapi langsung query agregasi

        $prefix = DB::getTablePrefix();

        $query = DB::table('account_charts')
            ->join('journal_entry_details', 'account_charts.id', '=', 'journal_entry_details.account_chart_id')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('account_charts.account_type', $accountType)
            ->whereNull('account_charts.parent_id') // Asumsi kita hanya menjumlahkan akun detail
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->select(
                'account_charts.account_code',
                'account_charts.account_name',
                DB::raw("SUM({$prefix}journal_entry_details.amount) as total")
            )
            ->groupBy('account_charts.account_code', 'account_charts.account_name')
            ->orderBy('account_charts.account_code');

        return $query->get();
        // ------------------------------------
    }

    private function calculateProfitForPeriod(string $startDate, string $endDate): float
    {
        $revenueTotal = $this->getGroupTotalForPeriod('REVENUE', $startDate, $endDate);
        $expenseTotal = $this->getGroupTotalForPeriod('EXPENSE', $startDate, $endDate);
        return (float) ($revenueTotal - $expenseTotal);
    }

    /**
     * Menghitung saldo akhir untuk sebuah grup akun (Neraca) secara KONSOLIDASI.
     */
    private function calculateAccountGroupBalance(string $accountType, string $endDate)
    {
        $prefix = DB::getTablePrefix();

        // --- PERBAIKAN KRUSIAL DI SINI ---
        // Query ini akan menjumlahkan saldo dari semua akun yang memiliki kode sama
        $query = DB::table('account_charts')
            ->join('journal_entry_details', 'account_charts.id', '=', 'journal_entry_details.account_chart_id')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('account_charts.account_type', $accountType)
            ->whereNull('account_charts.parent_id') // Asumsi kita hanya menjumlahkan akun detail
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->select(
                'account_charts.account_code',
                'account_charts.account_name',
                DB::raw("SUM(CASE WHEN {$prefix}journal_entry_details.entry_type = {$prefix}account_charts.normal_balance THEN {$prefix}journal_entry_details.amount ELSE -{$prefix}journal_entry_details.amount END) as balance")
            )
            ->groupBy('account_charts.account_code', 'account_charts.account_name')
            ->orderBy('account_charts.account_code');

        return $query->get();
        // ------------------------------------
    }

    private function getGroupTotalForPeriod(string $accountType, string $startDate, string $endDate)
    {
        $prefix = DB::getTablePrefix();

        $accountIds = AccountChart::where('account_type', $accountType)->whereDoesntHave('children')->pluck('id');
        if ($accountIds->isEmpty()) return 0;

        $result = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $accountIds)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->selectRaw("SUM(CASE WHEN {$prefix}journal_entry_details.entry_type = 'CREDIT' THEN {$prefix}journal_entry_details.amount ELSE -{$prefix}journal_entry_details.amount END) as total_sum")
            ->first();

        $sum = $result->total_sum ?? 0;
        return $accountType === 'EXPENSE' ? -(float)$sum : (float)$sum;
    }




    /**
     * Buku Besar Pembantu Simpanan Anggota
     */
    /**
     * Menghasilkan Laporan Buku Besar Pembantu Simpanan secara Konsolidasi.
     */
    public function generateConsolidatedMemberSavingsLedger()
    {
        try {
            $memberId = $this->payload['member_id'];
            $savingsTypeId = $this->payload['cooperation_savings_type_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $member = Member::findOrFail($memberId);
            $savingsType = CooperationSavingsType::findOrFail($savingsTypeId);

            // 1. Hitung Saldo Awal Konsolidasi
            $beginningBalance = $this->getConsolidatedSavingsBalanceAsOf($memberId, $savingsTypeId, Carbon::parse($startDate)->subDay()->toDateString());

            // 2. Ambil semua transaksi dalam periode (tanpa filter business_id)
            $transactions = CooperationMemberSavingsTransaction::query()
                ->where('member_id', $memberId)
                ->where('cooperation_savings_type_id', $savingsTypeId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->with('journalEntry.business:id,name') // Ambil info unit pencatat
                ->orderBy('transaction_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // 3. Hitung Saldo Berjalan (Running Balance)
            $runningBalance = $beginningBalance;
            $processedTransactions = $transactions->map(function ($tx) use (&$runningBalance) {
                $deposit = $tx->transaction_type === 'DEPOSIT' ? (float) $tx->amount : 0;
                $withdrawal = $tx->transaction_type === 'WITHDRAWAL' ? (float) $tx->amount : 0;

                $runningBalance += ($deposit - $withdrawal);

                return [
                    'date' => $tx->transaction_date,
                    'description' => $tx->description,
                    'business_unit' => $tx->journalEntry->business->name ?? 'N/A', // Unit yang mencatat
                    'deposit' => $deposit,
                    'withdrawal' => $withdrawal,
                    'balance' => $runningBalance,
                    'journal_entry_id' => $tx->journal_entry_id, // Untuk link/drill-down
                ];
            });

            $endingBalance = $runningBalance;

            // 4. Susun Laporan
            $report = [
                'report_name' => 'Buku Besar Pembantu Simpanan (Konsolidasi)',
                'period' => "{$startDate} to {$endDate}",
                'member_details' => ['id' => $member->id, 'name' => $member->name],
                'savings_type_details' => ['id' => $savingsType->id, 'name' => $savingsType->name],
                'beginning_balance' => $beginningBalance,
                'transactions' => $processedTransactions->toArray(),
                'ending_balance' => $endingBalance,
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method pendukung baru untuk menghitung saldo simpanan konsolidasi pada tanggal tertentu.
     */
    private function getConsolidatedSavingsBalanceAsOf(int $memberId, int $savingsTypeId, string $endDate): float
    {
        // Query dasar tanpa filter business_id
        $query = CooperationMemberSavingsTransaction::query()
            ->where('member_id', $memberId)
            ->where('cooperation_savings_type_id', $savingsTypeId)
            ->where('transaction_date', '<=', $endDate);

        $totalDeposits = (clone $query)->where('transaction_type', 'DEPOSIT')->sum('amount');
        $totalWithdrawals = (clone $query)->where('transaction_type', 'WITHDRAWAL')->sum('amount');

        return (float) ($totalDeposits - $totalWithdrawals);
    }
}
