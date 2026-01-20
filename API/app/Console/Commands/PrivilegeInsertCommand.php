<?php

namespace App\Console\Commands;

use App\Models\PrivilegeModel;
use Exception;
use Illuminate\Console\Command;

class PrivilegeInsertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'privilege:insert
                            {code}
                            {description?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert new privilege';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Collect input
        $input = [
            'tp_code' => $this->argument('code'),
            'tp_description' => $this->argument('description') ?? '',
        ];

        // Try insert data
        try {
            PrivilegeModel::insert($input);
            $this->info('New privilege inserted');
        } catch (Exception $e) {

            switch ($e->getCode()) {
                case '23000':
                    return $this->warn("Privilege [{$input['tp_code']}] already exists");
                    break;

                default;
                    return $this->error($e->getMessage());
                    break;
            }
        }
    }
}
