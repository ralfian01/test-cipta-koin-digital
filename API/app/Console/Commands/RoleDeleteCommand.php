<?php

namespace App\Console\Commands;

use App\Models\RoleModel;
use Exception;
use Illuminate\Console\Command;

class RoleDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:delete {id_or_code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete role';

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
        $role = RoleModel::where('tr_id', '=', $id)
            ->orWhere('tr_code', 'LIKE', "%{$id}%");

        if (!$role->exists()) {
            return $this->error('Data not found');
        }

        // Try delete data
        try {
            $role->delete();
            $this->info('Role deleted');
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
