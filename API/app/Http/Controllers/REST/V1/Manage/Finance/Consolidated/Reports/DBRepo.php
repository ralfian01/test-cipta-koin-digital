<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\Reports;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountChart;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CooperationMemberSavingsTransaction;
use App\Models\CooperationSavingsType;
use App\Models\FinanceContact;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{

    /**
     * =================================================
     * PERHITUNGAN HASIL USAHA
     * =================================================
     */

    /**
     * Menghasilkan Laporan Laba Rugi (PHU) Konsolidasi.
     */
    public function generateConsolidatedIncomeStatement()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // Panggil method helper yang sudah diperbaiki
            $revenues = $this->calculateLeafAccountTotals('REVENUE', $startDate, $endDate);
            $totalRevenue = $revenues->sum('total');

            $expenses = $this->calculateLeafAccountTotals('EXPENSE', $startDate, $endDate);
            $totalExpense = $expenses->sum('total');

            $report = [
                'report_name' => 'Perhitungan Hasil Usaha',
                'period' => "{$startDate} to {$endDate}",
                'revenues' => [
                    'accounts' => $revenues->toArray(),
                    'total' => $totalRevenue,
                ],
                'expenses' => [
                    'accounts' => $expenses->toArray(),
                    'total' => $totalExpense,
                ],
                'net_profit' => $totalRevenue - $totalExpense,
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper untuk menghitung total pergerakan untuk grup akun (Laba/Rugi).
     *
     * @param string $accountType 'REVENUE' atau 'EXPENSE'
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Support\Collection
     */
    private function calculateLeafAccountTotals(string $accountType, string $startDate, string $endDate)
    {
        $accounts = AccountChart::query()
            ->whereHas('category', function ($query) use ($accountType) {
                $query->where('account_type', $accountType);
            })
            ->whereDoesntHave('children')
            ->with('category')
            ->orderBy('account_code', 'asc')
            ->get();

        $results = collect();

        foreach ($accounts as $account) {
            $query = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_details.account_chart_id', $account->id)
                ->whereBetween('journal_entries.entry_date', [$startDate, $endDate]);

            $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
            $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

            $total = ($account->category->normal_balance === 'CREDIT')
                ? ($totalCredits - $totalDebits)
                : ($totalDebits - $totalCredits);

            if ((float) $total != 0) {
                $results->push([
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'total' => (float) $total,
                ]);
            }
        }

        return $results;
    }


    /**
     * =================================================
     * NERACA
     * =================================================
     */

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
                'report_name' => 'Neraca',
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
     * =================================================
     * RASIO KEUANGAN
     * =================================================
     */

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
     * =================================================
     * ARUS KAS
     * =================================================
     */

    /**
     * Menghasilkan Laporan Arus Kas Konsolidasi.
     */
    public function generateConsolidatedCashFlowStatement()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $cashAccountIds = AccountChart::query()
                ->whereHas('category', function ($query) {
                    $query->where('is_cash_equivalent', true);
                })
                ->orderBy('account_code', 'asc')
                ->pluck('id');

            if ($cashAccountIds->isEmpty()) {
                throw new Exception("No accounts are categorized as 'Cash & Bank' for this business.");
            }

            // Panggil method helper yang sudah diperbaiki
            $beginningBalance = $this->getCashBalanceAsOf($cashAccountIds, Carbon::parse($startDate)->subDay()->toDateString());
            $endingBalance = $this->getCashBalanceAsOf($cashAccountIds, $endDate);

            $operating = $this->getCashFlowForActivity('OPERATING', $cashAccountIds, $startDate, $endDate);
            $investing = $this->getCashFlowForActivity('INVESTING', $cashAccountIds, $startDate, $endDate);
            $financing = $this->getCashFlowForActivity('FINANCING', $cashAccountIds, $startDate, $endDate);

            $netCashFlow = $operating['total'] + $investing['total'] + $financing['total'];

            $report = [
                'report_name' => 'Laporan Arus Kas Konsolidasi',
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
            return (object)['status' => false, 'message' => [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace()
            ]];
        }
    }

    private function getCashBalanceAsOf($cashAccountIds, string $endDate): float
    {
        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $cashAccountIds)
            ->where('journal_entries.entry_date', '<=', $endDate);

        $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
        $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

        return (float) ($totalDebits - $totalCredits);
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

        // --- PERBAIKAN KRUSIAL 2: Query untuk "sisi lawan" ---
        $flows = DB::table('journal_entry_details as jed')
            // Join ke account_charts, lalu join ke account_chart_categories
            ->join('account_charts as ac', 'jed.account_chart_id', '=', 'ac.id')
            ->join('account_chart_categories as acc', 'ac.account_chart_category_id', '=', 'acc.id')
            ->whereIn('jed.journal_entry_id', $cashJournalEntryIds)
            // Filter berdasarkan 'cash_flow_activity' dari tabel kategori
            ->where('acc.cash_flow_activity', $activityType)
            ->select(
                'ac.account_name',
                DB::raw("SUM(CASE WHEN {$prefix}jed.entry_type = 'CREDIT' THEN {$prefix}jed.amount ELSE -{$prefix}jed.amount END) as total_flow")
            )
            ->groupBy('ac.account_name')
            ->get();
        // -------------------------------------------------------

        $filteredFlows = $flows->filter(fn($flow) => (float)$flow->total_flow != 0)->values();
        return ['accounts' => $filteredFlows->toArray(), 'total' => (float) $filteredFlows->sum('total_flow')];
    }

    /**
     * =================================================
     * LAPORAN PERUBAHAN MODAL
     * =================================================
     */

    /**
     * Menghasilkan Laporan Perubahan Modal Konsolidasi.
     */
    public function generateConsolidatedEquityChangeStatement()
    {
        try {
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];
            $beginningDate = Carbon::parse($endDate)->subDay()->toDateString();

            $beginningEquityAccounts = $this->calculateAccountGroupBalance('EQUITY', $beginningDate);
            $beginningEquity = $beginningEquityAccounts->sum('balance');

            $profitForPeriod = $this->generateConsolidatedIncomeStatement()->data['net_profit'];

            // TODO: Logika untuk menghitung setoran dan penarikan modal
            $capitalInjections = 0;
            $capitalWithdrawals = 0;

            $endingEquity = $beginningEquity + $profitForPeriod + $capitalInjections - $capitalWithdrawals;

            $report = [
                'report_name' => 'Laporan Perubahan Modal Konsolidasi',
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
        $accountsQuery = AccountChart::query()
            ->whereHas('category', fn($q) => $q->where('account_type', $accountType))
            ->whereDoesntHave('children')
            ->with('category')
            ->orderBy('account_code', 'asc');
        $accounts = $accountsQuery->get();

        $results = collect();

        foreach ($accounts as $account) {
            $query = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_details.account_chart_id', $account->id)
                ->where('journal_entries.entry_date', '<=', $endDate);

            $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
            $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

            $balance = ($account->category->normal_balance === 'DEBIT')
                ? ($totalDebits - $totalCredits)
                : ($totalCredits - $totalDebits);

            if ((float) $balance != 0) {
                $results->push([
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'balance' => (float) $balance,
                ]);
            }
        }
        return $results;
    }

    private function getGroupTotalForPeriod(string $accountType, string $startDate, string $endDate)
    {
        $accountIdsQuery = AccountChart::query()
            ->whereHas('category', fn($q) => $q->where('account_type', $accountType))
            ->whereDoesntHave('children');
        // if ($businessId) $accountIdsQuery->where('business_id', $businessId);
        $accountIds = $accountIdsQuery->pluck('id');

        if ($accountIds->isEmpty()) return 0;

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('account_charts', 'journal_entry_details.account_chart_id', '=', 'account_charts.id')
            ->join('account_chart_categories', 'account_charts.account_chart_category_id', '=', 'account_chart_categories.id')
            ->whereIn('journal_entry_details.account_chart_id', $accountIds)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate]);

        // if ($businessId) $query->where('journal_entries.business_id', $businessId);

        $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
        $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

        $normalBalance = ($accountType === 'REVENUE') ? 'CREDIT' : 'DEBIT';
        return $normalBalance === 'CREDIT' ? ($totalCredits - $totalDebits) : ($totalDebits - $totalCredits);

        // $prefix = DB::getTablePrefix();

        // $accountIds = AccountChart::where('account_type', $accountType)->whereDoesntHave('children')->pluck('id');
        // if ($accountIds->isEmpty()) return 0;

        // $result = DB::table('journal_entry_details')
        //     ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
        //     ->whereIn('journal_entry_details.account_chart_id', $accountIds)
        //     ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
        //     ->selectRaw("SUM(CASE WHEN {$prefix}journal_entry_details.entry_type = 'CREDIT' THEN {$prefix}journal_entry_details.amount ELSE -{$prefix}journal_entry_details.amount END) as total_sum")
        //     ->first();

        // $sum = $result->total_sum ?? 0;
        // return $accountType === 'EXPENSE' ? -(float)$sum : (float)$sum;
    }




    /**
     * =================================================
     * BUKU BESAR
     * =================================================
     */

    public function getConsolidatedLedger()
    {
        try {
            $accountCode = $this->payload['account_code'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // 1. Dapatkan semua akun dari semua bisnis yang cocok dengan kode ini
            $accounts = AccountChart::with('category')->where('account_code', $accountCode)->get();
            if ($accounts->isEmpty()) {
                throw new Exception("No accounts found with code {$accountCode}.");
            }
            $accountIds = $accounts->pluck('id');
            // Ambil detail dari akun pertama sebagai referensi (asumsi konsisten)
            $referenceAccount = $accounts->first();

            // 2. Hitung Saldo Awal Gabungan
            $beginningBalance = $this->getConsolidatedBalanceAsOf($accountIds, Carbon::parse($startDate)->subDay()->toDateString());

            // 3. Ambil semua transaksi gabungan dalam periode
            $transactions = DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
                ->join('business as b', 'je.business_id', '=', 'b.id') // Join ke tabel business
                ->whereIn('jed.account_chart_id', $accountIds)
                ->whereBetween('je.entry_date', [$startDate, $endDate])
                ->select('je.entry_date', 'je.description', 'jed.entry_type', 'jed.amount', 'b.name as business_name')
                ->orderBy('je.entry_date', 'asc')->orderBy('je.id', 'asc')
                ->get();

            // 4. Hitung Saldo Berjalan
            $runningBalance = $beginningBalance;
            $processedTransactions = $transactions->map(function ($tx) use (&$runningBalance, $referenceAccount) {
                $debit = $tx->entry_type === 'DEBIT' ? (float) $tx->amount : 0;
                $credit = $tx->entry_type === 'CREDIT' ? (float) $tx->amount : 0;

                if ($referenceAccount->category->normal_balance === 'DEBIT') {
                    $runningBalance += ($debit - $credit);
                } else {
                    $runningBalance += ($credit - $debit);
                }

                return [
                    'date' => $tx->entry_date,
                    'business_name' => $tx->business_name, // Tambahkan nama bisnis
                    'description' => $tx->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $runningBalance,
                ];
            });

            $report = [
                'report_name' => 'Buku Besar (Konsolidasi)',
                'period' => "{$startDate} to {$endDate}",
                'account_details' => [
                    'account_code' => $referenceAccount->account_code,
                    'account_name' => $referenceAccount->account_name,
                    'normal_balance' => $referenceAccount->category->normal_balance,
                ],
                'beginning_balance' => $beginningBalance,
                'transactions' => $processedTransactions->toArray(),
                'ending_balance' => $runningBalance,
            ];
            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    private function getConsolidatedBalanceAsOf($accountIds, string $endDate): float
    {
        // Ambil saldo normal dari akun pertama sebagai referensi
        $referenceAccount = AccountChart::with('category')->find($accountIds->first());
        if (!$referenceAccount) return 0;

        $totalDebits = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $accountIds)
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->where('journal_entry_details.entry_type', 'DEBIT')
            ->sum('journal_entry_details.amount');

        $totalCredits = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $accountIds)
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->where('journal_entry_details.entry_type', 'CREDIT')
            ->sum('journal_entry_details.amount');

        return ($referenceAccount->category->normal_balance === 'DEBIT')
            ? (float) ($totalDebits - $totalCredits)
            : (float) ($totalCredits - $totalDebits);
    }

    // Buku besar pembantu pendapatan
    public function getConsolidatedArLedger()
    {
        try {
            $endDate = $this->payload['end_date'];

            // 1. Ambil semua kontak CUSTOMER yang relevan
            $query = FinanceContact::query()
                ->where('contact_type', 'CUSTOMER')
                ->with('business:id,name');

            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
            }

            $allContacts = $query->get();

            // 2. Kelompokkan kontak berdasarkan nama yang dinormalisasi
            $groupedByName = $allContacts->groupBy(function ($item) {
                return trim(strtolower($item['name']));
            });

            $summaryList = [];

            // 3. Proses setiap grup nama kontak unik
            foreach ($groupedByName as $nameGroup) {
                $firstContact = $nameGroup->first();
                $businessBreakdown = [];
                $totalBalance = 0;

                // 4. Iterasi setiap entri kontak di dalam grup (per bisnis)
                foreach ($nameGroup as $contactInBusiness) {
                    // Hitung saldo piutang HANYA untuk kontak ini di bisnis ini
                    $balanceInBusiness = $this->getArContactBalanceAsOf($contactInBusiness->id, $endDate, $contactInBusiness->business_id);

                    // Hanya tampilkan jika ada saldo
                    if ((float)$balanceInBusiness != 0) {
                        $businessBreakdown[] = [
                            'business_id' => $contactInBusiness->business_id,
                            'business_name' => $contactInBusiness->business->name,
                            'balance' => (float)$balanceInBusiness,
                        ];
                        $totalBalance += $balanceInBusiness;
                    }
                }

                // 5. Hanya tambahkan ke laporan jika total saldo tidak nol
                if ((float)$totalBalance != 0) {
                    $summaryList[] = [
                        'contact_name' => $firstContact->name,
                        'total_balance' => (float)$totalBalance,
                        'business_breakdown' => $businessBreakdown,
                    ];
                }
            }

            // 6. Susun laporan akhir
            $report = [
                'report_name' => 'Ringkasan Piutang Usaha (Konsolidasi)',
                'as_of_date' => $endDate,
                'summary' => $summaryList,
                'grand_total' => collect($summaryList)->sum('total_balance'),
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper method yang sama persis seperti di Reports/DBRepo utama.
     */
    private function getArContactBalanceAsOf(int $contactId, string $endDate, int $businessId): float
    {
        $totalInvoiced = Invoice::where('contact_id', $contactId)
            ->where('business_id', $businessId)
            ->where('invoice_date', '<=', $endDate)->sum('total_amount');

        $totalPaid = InvoicePayment::whereHas('invoice', fn($q) => $q->where('contact_id', $contactId)->where('business_id', $businessId))
            ->where('payment_date', '<=', $endDate)->sum('amount');

        return (float) ($totalInvoiced - $totalPaid);
    }


    public function getConsolidatedApLedger()
    {
        try {
            $endDate = $this->payload['end_date'];

            // 1. Ambil semua kontak VENDOR yang relevan
            $query = FinanceContact::query()
                ->where('contact_type', 'VENDOR') // <-- Perubahan
                ->with('business:id,name');

            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
            }

            $allContacts = $query->get();

            // 2. Kelompokkan kontak berdasarkan nama yang dinormalisasi
            $groupedByName = $allContacts->groupBy(function ($item) {
                return trim(strtolower($item['name']));
            });

            $summaryList = [];

            // 3. Proses setiap grup nama kontak unik
            foreach ($groupedByName as $nameGroup) {
                $firstContact = $nameGroup->first();
                $businessBreakdown = [];
                $totalBalance = 0;

                // 4. Iterasi setiap entri kontak di dalam grup (per bisnis)
                foreach ($nameGroup as $contactInBusiness) {
                    // Hitung saldo utang HANYA untuk kontak ini di bisnis ini
                    $balanceInBusiness = $this->getApContactBalanceAsOf($contactInBusiness->id, $endDate, $contactInBusiness->business_id);

                    if ((float)$balanceInBusiness != 0) {
                        $businessBreakdown[] = [
                            'business_id' => $contactInBusiness->business_id,
                            'business_name' => $contactInBusiness->business->name,
                            'balance' => (float)$balanceInBusiness,
                        ];
                        $totalBalance += $balanceInBusiness;
                    }
                }

                // 5. Hanya tambahkan ke laporan jika total saldo tidak nol
                if ((float)$totalBalance != 0) {
                    $summaryList[] = [
                        'contact_name' => $firstContact->name,
                        'total_balance' => (float)$totalBalance,
                        'business_breakdown' => $businessBreakdown,
                    ];
                }
            }

            // 6. Susun laporan akhir
            $report = [
                'report_name' => 'Ringkasan Utang Usaha (Konsolidasi)',
                'as_of_date' => $endDate,
                'summary' => $summaryList,
                'grand_total' => collect($summaryList)->sum('total_balance'),
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper method untuk menghitung saldo utang.
     */
    private function getApContactBalanceAsOf(int $contactId, string $endDate, int $businessId): float
    {
        // --- PERUBAHAN DI SINI ---
        $totalBilled = Bill::where('contact_id', $contactId)
            ->where('business_id', $businessId)
            ->where('bill_date', '<=', $endDate)->sum('total_amount');

        $totalPaid = BillPayment::whereHas('bill', fn($q) => $q->where('contact_id', $contactId)->where('business_id', $businessId))
            ->where('payment_date', '<=', $endDate)->sum('amount');
        // -------------------------

        return (float) ($totalBilled - $totalPaid);
    }


    public function getConsolidatedFaLedger()
    {
        try {
            $endDate = $this->payload['end_date'];

            // 1. Inisialisasi Query (TANPA filter business_id)
            $query = FixedAsset::query()
                ->with([
                    'business:id,name',
                    'depreciationSetting',
                ]);

            // 2. Tambahkan agregasi withSum untuk penyusutan yang sudah di-posting HINGGA end_date
            $query->withSum([
                'depreciationSchedules as posted_depreciation_sum' => function ($q) use ($endDate) {
                    $q->where('status', 'POSTED')->where('depreciation_date', '<=', $endDate);
                }
            ], 'depreciation_amount');

            // 3. Terapkan filter opsional
            if (isset($this->payload['status'])) {
                $query->where('status', $this->payload['status']);
            }
            if (isset($this->payload['keyword'])) {
                $keyword = $this->payload['keyword'];
                $query->where(function ($q) use ($keyword) {
                    $q->where('asset_name', 'LIKE', "%{$keyword}%")
                        ->orWhere('asset_code', 'LIKE', "%{$keyword}%");
                });
            }

            // 4. Paginasi dan eksekusi query
            $perPage = $this->payload['per_page'] ?? 15;
            $assets = $query->orderBy('business_id', 'asc')->orderBy('acquisition_date', 'desc')->paginate($perPage);

            // 5. Lakukan perhitungan nilai buku untuk setiap aset
            $assets->each(function ($asset) {
                $this->appendCalculatedValues($asset);
            });

            // 6. Hitung Grand Total untuk semua aset (bukan hanya yang di halaman ini)
            // Ini query tambahan, bisa di-cache untuk performa
            $grandTotals = $this->calculateGrandTotals($this->payload);

            $paginatedData = $assets->toArray();
            $paginatedData['grand_totals'] = $grandTotals; // Sisipkan grand total ke hasil paginasi

            return (object)['status' => true, 'data' => $paginatedData];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    private function appendCalculatedValues(FixedAsset $asset): void
    {
        $openingBalance = (float)($asset->depreciationSetting->opening_balance_accumulated_depreciation ?? 0);
        $postedDepreciation = (float)($asset->posted_depreciation_sum ?? 0);
        $totalAccumulatedDepreciation = $openingBalance + $postedDepreciation;
        $bookValue = (float)$asset->acquisition_cost - $totalAccumulatedDepreciation;

        $asset->total_accumulated_depreciation = $totalAccumulatedDepreciation;
        $asset->book_value = $bookValue;
    }

    private function calculateGrandTotals(array $filters): array
    {
        // Query ini MENGULANG query utama TANPA paginasi untuk mendapatkan semua aset yang cocok
        // dan melakukan SUM di level database untuk efisiensi.
        $query = FixedAsset::query();
        // Terapkan filter yang sama
        if (isset($filters['status'])) $query->where('status', $filters['status']);
        if (isset($filters['keyword'])) { /* ... logika keyword ... */
        }

        $allMatchingAssets = $query->get();
        $totalAcquisitionCost = $allMatchingAssets->sum('acquisition_cost');

        // Hitung total nilai buku untuk semua aset yang cocok
        $totalBookValue = $allMatchingAssets->reduce(function ($carry, $asset) use ($filters) {
            $endDate = $filters['end_date'];
            $opening = (float)($asset->depreciationSetting->opening_balance_accumulated_depreciation ?? 0);
            $posted = (float)$asset->depreciationSchedules()
                ->where('status', 'POSTED')->where('depreciation_date', '<=', $endDate)
                ->sum('depreciation_amount');
            $bookValue = (float)$asset->acquisition_cost - ($opening + $posted);
            return $carry + $bookValue;
        }, 0);

        return [
            'total_acquisition_cost' => $totalAcquisitionCost,
            'total_book_value' => $totalBookValue,
        ];
    }

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
