<?php

namespace App\Console\Commands;

use App\Models\PrivilegeModel;
use Illuminate\Console\Command;

class PrivilegeViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'privilege:view';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get list of privileges';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $data = PrivilegeModel::all();

        if ($data->isEmpty()) {
            return $this->info('No data found');
        }

        // Return in table
        return $this->table(
            ['id', 'code', 'description'],
            $data->map(function ($item) {
                return [
                    $item->tp_id,
                    $item->tp_code,
                    $item->tp_description,
                ];
            })->toArray()
        );
    }
}
