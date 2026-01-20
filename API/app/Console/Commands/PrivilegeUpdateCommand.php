<?php

namespace App\Console\Commands;

use App\Models\PrivilegeModel;
use Exception;
use Illuminate\Console\Command;

class PrivilegeUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'privilege:update 
                            {id_or_code}
                            {--code=}
                            {--description=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update privilege data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get id
        $id = $this->argument('id_or_code');

        // Collect input
        $input = array_filter(
            [
                'tp_code' => $this->option('code') ?? null,
                'tp_description' => $this->option('description') ?? null,
            ],
            // Remove null value
            fn($value) => !is_null($value)
        );

        if (empty($input)) {
            return $this->warn('No data updated');
        }

        // Find by id or code
        if (is_int($id)) {
            $privilege = PrivilegeModel::where('tp_id', '=', $id);
        } else {
            $privilege = PrivilegeModel::where('tp_code', '=', "{$id}");
        }

        // If data does not exist
        if (!$privilege->exists()) {
            return $this->warn('Data not found');
        }

        // Select first row
        $privilege = $privilege->first();

        // Try update data
        try {
            $privilege->update($input);
            return $this->info('Privilege updated');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
