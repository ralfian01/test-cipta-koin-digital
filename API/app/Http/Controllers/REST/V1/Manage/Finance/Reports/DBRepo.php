<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountChart;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\FinanceContact;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Menghasilkan Laporan Buku Besar untuk satu akun spesifik.
     */
    public function generateGeneralLedger()
    {
        try {
            $businessId = $this->payload['business_id'];
            $accountId = $this->payload['account_chart_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $account = AccountChart::with('category')
                ->where('id', $accountId)
                ->where('business_id', $businessId)
                ->firstOrFail();

            // 1. Hitung Saldo Awal
            $beginningBalance = $this->getAccountBalanceAsOf($account, Carbon::parse($startDate)->subDay()->toDateString(), $businessId);

            // 2. Ambil semua transaksi dalam periode
            $transactions = DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
                ->where('jed.account_chart_id', $accountId)
                ->where('je.business_id', $businessId)
                ->whereBetween('je.entry_date', [$startDate, $endDate])
                ->select('je.entry_date', 'je.description', 'jed.entry_type', 'jed.amount')
                ->orderBy('je.entry_date', 'desc')
                ->orderBy('je.id', 'desc')
                ->get();

            // 3. Hitung Saldo Berjalan (Running Balance)
            $runningBalance = $beginningBalance;
            $endingBalance = $beginningBalance;
            $processedTransactions = $transactions->map(function ($tx) use (&$runningBalance, $account) {
                $debit = $tx->entry_type === 'DEBIT' ? (float) $tx->amount : 0;
                $credit = $tx->entry_type === 'CREDIT' ? (float) $tx->amount : 0;

                // --- PERUBAHAN KRUSIAL DI SINI ---
                // Akses 'normal_balance' dari relasi 'category'
                if ($account->category->normal_balance === 'DEBIT') {
                    $runningBalance += ($debit - $credit);
                } else {
                    $runningBalance += ($credit - $debit);
                }
                // ------------------------------------

                return [
                    'date' => $tx->entry_date,
                    'description' => $tx->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $runningBalance,
                ];
            });

            if ($processedTransactions->isNotEmpty()) {
                $endingBalance = $processedTransactions->last()['balance'];
            }

            // 4. Susun Laporan
            $report = [
                'report_name' => 'Buku Besar (General Ledger)',
                'business_id' => $businessId,
                'period' => "{$startDate} to {$endDate}",
                'account_details' => [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'normal_balance' => $account->category->normal_balance, // <-- Akses dari relasi
                ],
                'beginning_balance' => $beginningBalance,
                'transactions' => $processedTransactions->toArray(),
                'ending_balance' => $endingBalance,
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'code' => $e->getCode(),
            ]];
        }
    }

    /**
     * Method BARU: Menghasilkan laporan ringkasan untuk SEMUA kontak.
     */
    public function generateArLedgerSummary()
    {
        try {
            $businessId = $this->payload['business_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // 1. Ambil semua kontak CUSTOMER untuk bisnis ini
            $customers = FinanceContact::query()
                ->where('business_id', $businessId)
                ->where('contact_type', 'CUSTOMER')
                ->get();

            $summaryData = collect();

            // 2. Lakukan iterasi untuk setiap customer
            foreach ($customers as $customer) {
                // 3. Hitung Saldo Awal untuk customer ini
                $beginningBalance = $this->getArContactBalanceAsOf($customer->id, Carbon::parse($startDate)->subDay()->toDateString(), $businessId);

                // 4. Hitung Total Tagihan dalam periode
                $totalInvoicedInPeriod = Invoice::where('contact_id', $customer->id)
                    ->where('business_id', $businessId)
                    ->whereBetween('invoice_date', [$startDate, $endDate])
                    ->sum('total_amount');

                // 5. Hitung Total Pembayaran dalam periode
                $totalPaidInPeriod = InvoicePayment::whereHas('invoice', fn($q) => $q->where('contact_id', $customer->id)->where('business_id', $businessId))
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->sum('amount');

                // 6. Hitung Saldo Akhir
                $endingBalance = $beginningBalance + $totalInvoicedInPeriod - $totalPaidInPeriod;

                // 7. Hanya tampilkan kontak yang memiliki saldo awal, transaksi, atau saldo akhir
                if ($beginningBalance != 0 || $totalInvoicedInPeriod != 0 || $totalPaidInPeriod != 0 || $endingBalance != 0) {
                    $summaryData->push([
                        'contact_id' => $customer->id,
                        'contact_name' => $customer->name,
                        'beginning_balance' => (float) $beginningBalance,
                        'total_invoiced' => (float) $totalInvoicedInPeriod,
                        'total_paid' => (float) $totalPaidInPeriod,
                        'ending_balance' => (float) $endingBalance,
                    ]);
                }
            }

            // 8. Susun Laporan
            $report = [
                'report_name' => 'Ringkasan Buku Besar Pembantu Piutang',
                'period' => "{$startDate} to {$endDate}",
                'summary' => $summaryData->sortBy('contact_name')->values()->toArray(),
                'totals' => [
                    'beginning_balance' => $summaryData->sum('beginning_balance'),
                    'total_invoiced' => $summaryData->sum('total_invoiced'),
                    'total_paid' => $summaryData->sum('total_paid'),
                    'ending_balance' => $summaryData->sum('ending_balance'),
                ]
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ];
        }
    }

    public function generateArLedgerDetail()
    {
        try {
            $businessId = $this->payload['business_id'];
            $contactId = $this->payload['contact_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $contact = FinanceContact::findOrFail($contactId);
            if ($contact->contact_type !== 'CUSTOMER') throw new Exception("Contact is not a customer.");

            // 1. Hitung Saldo Awal Piutang untuk kontak ini
            $beginningBalance = $this->getArContactBalanceAsOf($contactId, Carbon::parse($startDate)->subDay()->toDateString(), $businessId);

            // 2. Ambil semua Invoices dan Payments dalam periode
            $invoices = Invoice::where('contact_id', $contactId)
                ->where('business_id', $businessId)
                ->whereBetween('invoice_date', [$startDate, $endDate])->get();

            $payments =
                InvoicePayment::whereHas('invoice', function ($query) use ($businessId, $contactId) {
                    $query->where('business_id', $businessId)
                        ->where('contact_id', $contactId);
                })
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->with('invoice:id,invoice_number') // Eager load nomor invoice untuk deskripsi
                ->get();

            // 3. Gabungkan dan urutkan semua transaksi
            $transactions = collect();
            foreach ($invoices as $invoice) {
                $transactions->push(['date' => $invoice->invoice_date, 'type' => 'INVOICE', 'description' => "Tagihan #{$invoice->invoice_number}", 'debit' => $invoice->total_amount, 'credit' => 0]);
            }
            foreach ($payments as $payment) {
                $transactions->push(['date' => $payment->payment_date, 'type' => 'PAYMENT', 'description' => "Pembayaran untuk Tagihan #{$payment->invoice->invoice_number}", 'debit' => 0, 'credit' => $payment->amount]);
            }
            $sortedTransactions = $transactions->sortBy('date');

            // 4. Hitung Saldo Berjalan
            $runningBalance = $beginningBalance;
            $processedTransactions = $sortedTransactions->map(function ($tx) use (&$runningBalance) {
                $runningBalance += ($tx['debit'] - $tx['credit']);
                $tx['balance'] = $runningBalance;
                return $tx;
            });

            $report = [
                'report_name' => 'Buku Besar Pembantu Piutang',
                'period' => "{$startDate} to {$endDate}",
                'contact_details' => ['id' => $contact->id, 'name' => $contact->name],
                'beginning_balance' => $beginningBalance,
                'transactions' => $processedTransactions->values()->toArray(),
                'ending_balance' => $runningBalance,
            ];
            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ];
        }
    }

    private function getArContactBalanceAsOf(int $contactId, string $endDate, int $businessId): float
    {
        $totalInvoiced = Invoice::where('contact_id', $contactId)->where('business_id', $businessId)
            ->where('invoice_date', '<=', $endDate)->sum('total_amount');
        $totalPaid =
            InvoicePayment::whereHas('invoice', function ($query) use ($businessId, $contactId) {
                $query->where('business_id', $businessId)
                    ->where('contact_id', $contactId);
            })
            ->where('payment_date', '<=', $endDate)->sum('amount');

        return (float) ($totalInvoiced - $totalPaid);
    }

    public function generateApLedgerSummary()
    {
        try {
            $businessId = $this->payload['business_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // 1. Ambil semua kontak vendor untuk bisnis ini
            $vendors = FinanceContact::query()
                ->where('business_id', $businessId)
                ->where('contact_type', 'VENDOR')
                ->get();

            $summaryData = collect();

            // 2. Lakukan iterasi untuk setiap vendor
            foreach ($vendors as $vendor) {
                // 3. Hitung Saldo Awal untuk vendor ini
                $beginningBalance = $this->getApContactBalanceAsOf($vendor->id, Carbon::parse($startDate)->subDay()->toDateString(), $businessId);

                // 4. Hitung Total Tagihan dalam periode
                $totalInvoicedInPeriod = Invoice::where('contact_id', $vendor->id)
                    ->where('business_id', $businessId)
                    ->whereBetween('invoice_date', [$startDate, $endDate])
                    ->sum('total_amount');

                // 5. Hitung Total Pembayaran dalam periode
                $totalPaidInPeriod = InvoicePayment::whereHas('invoice', fn($q) => $q->where('contact_id', $vendor->id)->where('business_id', $businessId))
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->sum('amount');

                // 6. Hitung Saldo Akhir
                $endingBalance = $beginningBalance + $totalInvoicedInPeriod - $totalPaidInPeriod;

                // 7. Hanya tampilkan kontak yang memiliki saldo awal, transaksi, atau saldo akhir
                if ($beginningBalance != 0 || $totalInvoicedInPeriod != 0 || $totalPaidInPeriod != 0 || $endingBalance != 0) {
                    $summaryData->push([
                        'contact_id' => $vendor->id,
                        'contact_name' => $vendor->name,
                        'beginning_balance' => (float) $beginningBalance,
                        'total_invoiced' => (float) $totalInvoicedInPeriod,
                        'total_paid' => (float) $totalPaidInPeriod,
                        'ending_balance' => (float) $endingBalance,
                    ]);
                }
            }

            // 8. Susun Laporan
            $report = [
                'report_name' => 'Ringkasan Buku Besar Pembantu Utang',
                'period' => "{$startDate} to {$endDate}",
                'summary' => $summaryData->sortBy('contact_name')->values()->toArray(),
                'totals' => [
                    'beginning_balance' => $summaryData->sum('beginning_balance'),
                    'total_invoiced' => $summaryData->sum('total_invoiced'),
                    'total_paid' => $summaryData->sum('total_paid'),
                    'ending_balance' => $summaryData->sum('ending_balance'),
                ]
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ];
        }
    }

    public function generateApLedgerDetail()
    {
        try {
            $businessId = $this->payload['business_id'];
            $contactId = $this->payload['contact_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $contact = FinanceContact::findOrFail($contactId);
            if ($contact->contact_type !== 'VENDOR') throw new Exception("Contact is not a vendor.");

            // 1. Hitung Saldo Awal Piutang untuk kontak ini
            $beginningBalance = $this->getApContactBalanceAsOf($contactId, Carbon::parse($startDate)->subDay()->toDateString(), $businessId);

            // 2. Ambil semua bills dan Payments dalam periode
            $bills = Bill::where('contact_id', $contactId)->where('business_id', $businessId)
                ->whereBetween('bill_date', [$startDate, $endDate])->get();
            $payments =
                BillPayment::whereHas('bill', function ($query) use ($businessId, $contactId) {
                    $query->where('business_id', $businessId)
                        ->where('contact_id', $contactId);
                })
                ->whereBetween('payment_date', [$startDate, $endDate])->get();

            // 3. Gabungkan dan urutkan semua transaksi
            $transactions = collect();
            foreach ($bills as $bill) {
                $transactions->push(['date' => $bill->bill_date, 'type' => 'bill', 'description' => "Tagihan #{$bill->bill_number}", 'debit' => $bill->total_amount, 'credit' => 0]);
            }
            foreach ($payments as $payment) {
                $transactions->push(['date' => $payment->payment_date, 'type' => 'PAYMENT', 'description' => "Pembayaran untuk Tagihan #{$payment->bill->bill_number}", 'debit' => 0, 'credit' => $payment->amount]);
            }
            $sortedTransactions = $transactions->sortBy('date');

            // 4. Hitung Saldo Berjalan
            $runningBalance = $beginningBalance;
            $processedTransactions = $sortedTransactions->map(function ($tx) use (&$runningBalance) {
                $runningBalance += ($tx['debit'] - $tx['credit']);
                $tx['balance'] = $runningBalance;
                return $tx;
            });

            $report = [
                'report_name' => 'Buku Besar Pembantu Utang',
                'period' => "{$startDate} to {$endDate}",
                'contact_details' => ['id' => $contact->id, 'name' => $contact->name],
                'beginning_balance' => $beginningBalance,
                'transactions' => $processedTransactions->values()->toArray(),
                'ending_balance' => $runningBalance,
            ];
            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    public function generateFixedAssetLedger()
    {
        try {
            $assetId = $this->payload['fixed_asset_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $asset = FixedAsset::with('depreciationSetting')->findOrFail($assetId);

            // 1. Hitung Saldo Awal (Nilai Buku pada H-1)
            $beginningDate = Carbon::parse($startDate)->subDay()->toDateString();
            $accumDepreBeginning = (float)($asset->depreciationSetting->opening_balance_accumulated_depreciation ?? 0);
            $postedDepreBeginning = $asset->depreciationSchedules()
                ->where('status', 'POSTED')
                ->where('depreciation_date', '<=', $beginningDate)
                ->sum('depreciation_amount');
            $beginningBookValue = (float)$asset->acquisition_cost - ($accumDepreBeginning + (float)$postedDepreBeginning);

            // 2. Ambil semua jadwal penyusutan yang di-posting dalam periode
            $schedulesInPeriod = $asset->depreciationSchedules()
                ->where('status', 'POSTED')
                ->whereBetween('depreciation_date', [$startDate, $endDate])
                ->orderBy('depreciation_date', 'asc')
                ->get();

            // 3. Bangun tabel transaksi
            $transactions = [];
            $runningBalance = $beginningBookValue;

            foreach ($schedulesInPeriod as $schedule) {
                $runningBalance -= (float)$schedule->depreciation_amount;
                $transactions[] = [
                    'date' => $schedule->depreciation_date,
                    'reference' => 'Beban Penyusutan',
                    'journal_id' => $schedule->posted_journal_entry_id,
                    'debit' => null, // Tidak ada penambahan nilai
                    'credit' => (float)$schedule->depreciation_amount,
                    'balance' => $runningBalance,
                ];
            }

            // 4. Susun Laporan
            $report = [
                'report_name' => 'Buku Besar Pembantu Aset Tetap',
                'period' => "{$startDate} to {$endDate}",
                'asset_details' => [
                    'id' => $asset->id,
                    'name' => $asset->asset_name,
                    'acquisition_cost' => (float)$asset->acquisition_cost,
                ],
                'beginning_balance' => $beginningBookValue,
                'transactions' => $transactions,
                'ending_balance' => $runningBalance,
            ];

            return (object)['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }



    private function getApContactBalanceAsOf(int $contactId, string $endDate, int $businessId): float
    {
        $totalbilld = Bill::where('contact_id', $contactId)->where('business_id', $businessId)
            ->where('bill_date', '<=', $endDate)->sum('total_amount');
        $totalPaid =
            BillPayment::whereHas('bill', function ($query) use ($businessId, $contactId) {
                $query->where('business_id', $businessId)
                    ->where('contact_id', $contactId);
            })
            ->where('payment_date', '<=', $endDate)->sum('amount');
        return (float) ($totalbilld - $totalPaid);
    }


    private function getAccountBalanceAsOf(AccountChart $account, string $endDate, int $businessId): float
    {
        $totalDebits = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_details.account_chart_id', $account->id)
            ->where('journal_entries.business_id', $businessId)
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->where('journal_entry_details.entry_type', 'DEBIT')
            ->sum('journal_entry_details.amount');

        $totalCredits = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_details.account_chart_id', $account->id)
            ->where('journal_entries.business_id', $businessId)
            ->where('journal_entries.entry_date', '<=', $endDate)
            ->where('journal_entry_details.entry_type', 'CREDIT')
            ->sum('journal_entry_details.amount');

        // --- PERUBAHAN KRUSIAL DI SINI ---
        // Akses 'normal_balance' dari relasi 'category' yang sudah ada di objek $account
        return ($account->category->normal_balance === 'DEBIT')
            ? (float) ($totalDebits - $totalCredits)
            : (float) ($totalCredits - $totalDebits);
        // ------------------------------------
    }



    public function generateEquityChangeStatement()
    {
        try {
            $businessId = $this->payload['business_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];
            $beginningDate = Carbon::parse($endDate)->subDay()->toDateString();

            $beginningEquityAccounts = $this->calculateAccountGroupBalance('EQUITY', $beginningDate, $businessId);
            $beginningEquity = $beginningEquityAccounts->sum('balance');

            $profitForPeriod = $this->generateIncomeStatement()->data['net_profit'];

            // TODO: Logika untuk menghitung setoran dan penarikan modal
            $capitalInjections = 0;
            $capitalWithdrawals = 0;

            $endingEquity = $beginningEquity + $profitForPeriod + $capitalInjections - $capitalWithdrawals;

            $report = [
                'report_name' => 'Laporan Perubahan Modal',
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


    public function generateCashFlowStatement()
    {
        try {
            $businessId = $this->payload['business_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            $cashAccountIds = AccountChart::query()
                ->where('business_id', $businessId)
                ->whereHas('category', function ($query) {
                    $query->where('is_cash_equivalent', true);
                })
                ->orderBy('account_code', 'asc')
                ->pluck('id');

            if ($cashAccountIds->isEmpty()) {
                throw new Exception("No accounts are categorized as 'Cash & Bank' for this business.");
            }

            // Panggil method helper yang sudah diperbaiki
            $beginningBalance = $this->getCashBalanceAsOf($cashAccountIds, Carbon::parse($startDate)->subDay()->toDateString(), $businessId);
            $endingBalance = $this->getCashBalanceAsOf($cashAccountIds, $endDate, $businessId);

            $operating = $this->getCashFlowForActivity('OPERATING', $cashAccountIds, $startDate, $endDate, $businessId);
            $investing = $this->getCashFlowForActivity('INVESTING', $cashAccountIds, $startDate, $endDate, $businessId);
            $financing = $this->getCashFlowForActivity('FINANCING', $cashAccountIds, $startDate, $endDate, $businessId);

            $netCashFlow = $operating['total'] + $investing['total'] + $financing['total'];

            $report = [
                'report_name' => 'Laporan Arus Kas',
                'business_id' => $businessId,
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

    private function getCashBalanceAsOf($cashAccountIds, string $endDate, int $businessId): float
    {
        // Method ini tidak perlu diubah karena sudah menerima $cashAccountIds
        // dan tidak bergantung pada 'normal_balance' secara langsung.
        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $cashAccountIds)
            ->where('journal_entries.business_id', $businessId)
            ->where('journal_entries.entry_date', '<=', $endDate);

        $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
        $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

        return (float) ($totalDebits - $totalCredits);
    }

    /**
     * Menghitung total arus kas untuk satu jenis aktivitas.
     * (Versi Final yang Disesuaikan dengan Arsitektur Baru)
     */
    private function getCashFlowForActivity(string $activityType, $cashAccountIds, string $startDate, string $endDate, int $businessId): array
    {
        $cashJournalEntryIds = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_chart_id', $cashAccountIds)
            ->where('journal_entries.business_id', $businessId)
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
            ->where('ac.business_id', $businessId)
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
     * Menghasilkan Laporan Laba Rugi (PHU) untuk satu unit bisnis spesifik.
     */
    public function generateIncomeStatement()
    {
        try {
            $businessId = $this->payload['business_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // Panggil method helper yang sudah diperbaiki
            $revenues = $this->calculateLeafAccountTotals('REVENUE', $startDate, $endDate, $businessId);
            $totalRevenue = $revenues->sum('total');

            $expenses = $this->calculateLeafAccountTotals('EXPENSE', $startDate, $endDate, $businessId);
            $totalExpense = $expenses->sum('total');

            $report = [
                'report_name' => 'Perhitungan Hasil Usaha',
                'business_id' => $businessId,
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
     * @param int $businessId
     * @return \Illuminate\Support\Collection
     */
    private function calculateLeafAccountTotals(string $accountType, string $startDate, string $endDate, int $businessId)
    {
        // 1. Ambil HANYA akun posting yang relevan, berdasarkan KATEGORI-nya
        $accounts = AccountChart::query()
            ->where('business_id', $businessId)
            // --- PERUBAHAN KRUSIAL DI SINI ---
            // Filter berdasarkan 'account_type' dari tabel relasi 'category'
            ->whereHas('category', function ($query) use ($accountType) {
                $query->where('account_type', $accountType);
            })
            ->whereDoesntHave('children')
            ->with('category')
            ->orderBy('account_code', 'asc')
            ->get();

        $results = collect();

        // 2. Lakukan iterasi dan hitung total untuk setiap akun posting
        foreach ($accounts as $account) {
            $query = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_details.account_chart_id', $account->id)
                ->where('journal_entries.business_id', $businessId)
                ->whereBetween('journal_entries.entry_date', [$startDate, $endDate]);

            $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
            $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

            // --- PERUBAHAN KRUSIAL DI SINI ---
            // Akses 'normal_balance' dari relasi 'category' yang sudah di-eager load
            $total = ($account->category->normal_balance === 'CREDIT')
                ? ($totalCredits - $totalDebits)
                : ($totalDebits - $totalCredits);
            // ------------------------------------

            // 3. Hanya tambahkan ke hasil jika totalnya tidak nol
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
     * Menghasilkan Laporan Rasio Keuangan.
     */
    public function generateRatios()
    {
        try {
            $businessId = $this->payload['business_id'];
            $startDate = $this->payload['start_date'];
            $endDate = $this->payload['end_date'];

            // 1. Ambil variabel dari Laporan Neraca (per end_date)
            $balanceSheetData = $this->generateBalanceSheet()->data;
            $totalAssets = $balanceSheetData['assets']['total'];
            $totalLiabilities = $balanceSheetData['liabilities']['total'];
            $totalEquity = $balanceSheetData['equity']['total'];

            // 2. Ambil variabel dari Laporan Laba Rugi (untuk periode start_date -> end_date)
            $incomeStatementData = $this->generateIncomeStatement()->data;
            $profit = $incomeStatementData['net_profit'];

            // 3. Hitung Rasio
            // Rasio A: Kemampuan membayar hutang
            $debtRatioValue = ($totalLiabilities > 0) ? ($totalAssets / $totalLiabilities) * 100 : 0;

            // Rasio B: Rentabilitas (Return on Equity)
            $profitabilityRatioValue = ($totalEquity > 0) ? ($profit / $totalEquity) * 100 : 0;

            // 4. Susun data laporan
            $report = [
                'report_name' => 'Laporan Rasio Keuangan',
                'business_id' => $businessId,
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

    /*
     * =================================================================================
     * METHOD PENDUKUNG UNTUK PERHITUNGAN
     * =================================================================================
     */


    /**
     * Menghasilkan Laporan Neraca untuk satu unit bisnis spesifik.
     */
    public function generateBalanceSheet()
    {
        try {
            $businessId = $this->payload['business_id'];
            $endDate = Carbon::parse($this->payload['end_date']);
            $startDate = isset($this->payload['start_date'])
                ? Carbon::parse($this->payload['start_date'])
                : $endDate->copy()->startOfYear();

            // Panggil method-method yang sudah diperbaiki
            $assets = $this->calculateAccountGroupBalance('ASSET', $endDate->toDateString(), $businessId);
            $totalAssets = $assets->sum('balance');

            $liabilities = $this->calculateAccountGroupBalance('LIABILITY', $endDate->toDateString(), $businessId);
            $totalLiabilities = $liabilities->sum('balance');

            $equityAccounts = $this->calculateAccountGroupBalance('EQUITY', $endDate->toDateString(), $businessId);
            $totalEquityAccounts = $equityAccounts->sum('balance');

            $profitForPeriod = $this->calculateProfitForPeriod($startDate->toDateString(), $endDate->toDateString(), $businessId);
            $totalEquity = $totalEquityAccounts + $profitForPeriod;

            $report = [
                'report_name' => 'Neraca',
                'business_id' => $businessId,
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

    private function calculateProfitForPeriod(string $startDate, string $endDate, ?int $businessId): float
    {
        $revenueTotal = $this->getGroupTotalForPeriod('REVENUE', $startDate, $endDate, $businessId);
        $expenseTotal = $this->getGroupTotalForPeriod('EXPENSE', $startDate, $endDate, $businessId);
        return (float) ($revenueTotal - $expenseTotal);
    }



    /**
     * Menghitung total pergerakan untuk grup akun (Laba/Rugi).
     * (Versi Final yang Robust)
     */
    private function getGroupTotalForPeriod(string $accountType, string $startDate, string $endDate, ?int $businessId): float
    {
        $accountIdsQuery = AccountChart::query()
            ->whereHas('category', fn($q) => $q->where('account_type', $accountType))
            ->whereDoesntHave('children');
        if ($businessId) $accountIdsQuery->where('business_id', $businessId);
        $accountIds = $accountIdsQuery->pluck('id');

        if ($accountIds->isEmpty()) return 0;

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('account_charts', 'journal_entry_details.account_chart_id', '=', 'account_charts.id')
            ->join('account_chart_categories', 'account_charts.account_chart_category_id', '=', 'account_chart_categories.id')
            ->whereIn('journal_entry_details.account_chart_id', $accountIds)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate]);

        if ($businessId) $query->where('journal_entries.business_id', $businessId);

        $totalDebits = (clone $query)->where('journal_entry_details.entry_type', 'DEBIT')->sum('journal_entry_details.amount');
        $totalCredits = (clone $query)->where('journal_entry_details.entry_type', 'CREDIT')->sum('journal_entry_details.amount');

        $normalBalance = ($accountType === 'REVENUE') ? 'CREDIT' : 'DEBIT';
        return $normalBalance === 'CREDIT' ? ($totalCredits - $totalDebits) : ($totalDebits - $totalCredits);
    }


    /**
     * Menghitung saldo akhir untuk sebuah grup akun (Neraca).
     * (Versi Final yang Robust)
     */
    private function calculateAccountGroupBalance(string $accountType, string $endDate, ?int $businessId)
    {
        $accountsQuery = AccountChart::query()
            ->whereHas('category', fn($q) => $q->where('account_type', $accountType))
            ->whereDoesntHave('children')
            ->with('category')
            ->orderBy('account_code', 'asc');
        if ($businessId) $accountsQuery->where('business_id', $businessId);
        $accounts = $accountsQuery->get();

        $results = collect();

        foreach ($accounts as $account) {
            $query = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_details.account_chart_id', $account->id)
                ->where('journal_entries.entry_date', '<=', $endDate);

            if ($businessId) $query->where('journal_entries.business_id', $businessId);

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
}
