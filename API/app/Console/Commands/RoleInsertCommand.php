<?php

namespace App\Console\Commands;

use App\Models\PrivilegeModel;
use App\Models\RoleModel;
use App\Models\RolePrivilegeModel;
use Exception;
use Illuminate\Console\Command;

class RoleInsertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:insert
                            {code}
                            {name}
                            {--privileges=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert new role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Collect input
        $input = [
            'tr_code' => $this->argument('code'),
            'tr_name' => $this->argument('name'),
        ];

        // Collect & check selected privileges
        if ($this->option('privileges') != null) {
            $optPrivilege = explode(',', str_replace(' ', '', $this->option('privileges')));

            $checkPrivilege = $this->checkPrivilege($optPrivilege);

            if (!$checkPrivilege->status) {
                return $this->warn($checkPrivilege->message);
            }
        } else {
            // Let user select privileges
            $privChoices = PrivilegeModel::all(['tp_code', 'tp_description'])
                ->map(function ($item) {
                    return "[{$item->tp_code}]: {$item->tp_description}";
                })
                ->toArray();

            $choicedPrivileges = $this->choice(...[
                'question' => "Please select privilege for [{$input['tr_code']}]",
                'choices' => $privChoices,
                'multiple' => true
            ]);

            foreach ($choicedPrivileges as &$choice) {
                if (preg_match('/\[([^\]]+)\]/', $choice, $matches)) {
                    $choice = $matches[1];
                }
            }

            $checkPrivilege = $this->checkPrivilege($choicedPrivileges);

            if (!$checkPrivilege->status) {
                return $this->warn($checkPrivilege->message);
            }
        }

        $selectedPrivileges = $checkPrivilege->data;

        // Try insert data
        try {
            $insert = RoleModel::create($input);

            if ($insert) {
                // Insert role privilege
                $rolePrivilege = [];

                foreach ($selectedPrivileges as $id) {
                    $rolePrivilege[] = [
                        'tr_id' => $insert->tr_id,
                        'tp_id' => $id
                    ];
                }

                RolePrivilegeModel::insert($rolePrivilege);

                return $this->info('New role inserted');
            }
        } catch (Exception $e) {

            switch ($e->getCode()) {
                case '23000':
                    return $this->warn("Role [{$input['tr_code']}] already exists");
                    break;

                default;
                    return $this->error($e->getMessage());
                    break;
            }
        }
    }

    /**
     * Check privileges
     * @return object
     */
    private function checkPrivilege(array $arrPriv = [])
    {
        // Check privilege availability
        $privilege = PrivilegeModel::whereIn('tp_id', $arrPriv)
            ->orWhereIn('tp_code', $arrPriv)
            ->get(['tp_id', 'tp_code'])
            ->toArray();

        // Make sure id or code available
        $foundIds = array_column($privilege, 'tp_id');
        $foundCodes = array_column($privilege, 'tp_code');

        $missingIds = array_diff(
            $arrPriv,
            array_merge($foundIds, $foundCodes)
        );

        if (!empty($missingIds)) {
            return (object) [
                'status' => false,
                'message' => 'The following IDs are not available: ' . implode(', ', $missingIds),
            ];
        }

        return (object) [
            'status' => true,
            'data' => $foundIds, // Return id
        ];
    }
}
