<?php

namespace App\Console\Commands;

use App\Models\PrivilegeModel;
use Exception;
use Illuminate\Console\Command;

class PrivilegeDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'privilege:delete
                            {id_or_code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete privilege';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get id
        $id = $this->argument('id_or_code');

        // Confirmation
        $accept = $this->confirm("Remove privilege? \n Data in the roles and accounts tables will be affected \n");
        if (!$accept) {
            return $this->warn('Deletion canceled');
        }

        // Find by id or code
        $privilege = PrivilegeModel::where('tp_id', '=', $id)
            ->orWhere('tp_code', 'LIKE', "%{$id}%");

        if (!$privilege->exists()) {
            return $this->error('Data not found');
        }

        // Try delete data
        try {
            $privilege->delete();
            return $this->info('Privilege deleted');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
