<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Exports\JournalEntriesExport; // Import kelas Export
use Maatwebsite\Excel\Facades\Excel; // Import facade Excel

class Export extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = []
    ) {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    /**
     * @var array
     * Payloadnya sama dengan filter di endpoint GET
     */
    protected $payloadRules = [
        'business_id' => 'nullable|integer|exists:business,id',
        'start_date' => 'nullable|date_format:Y-m-d',
        'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        'keyword' => 'nullable|string|min:2',
    ];

    protected $privilegeRules = [
        "JOURNAL_MANAGE_VIEW",
        "JOURNAL_MANAGE_EXPORT",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        return $this->export();
    }

    public function export()
    {
        try {
            $fileName = 'jurnal_umum_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Panggil Laravel Excel untuk men-download file
            // Kita pass payload (filter) ke constructor kelas Export
            return Excel::download(new JournalEntriesExport($this->payload), $fileName);
        } catch (\Exception $e) {
            return $this->error(500, ['reason' => $e->getMessage()]);
        }
    }
}
