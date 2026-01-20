<?php

namespace App\Console\Commands;

use App\Models\RoleModel;
use App\Models\RoleViewModel;
use Illuminate\Console\Command;
use stdClass;

class RoleViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:view';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View list of roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $data =
            RoleModel::select(['tr_id', 'tr_code', 'tr_name'])
            ->getWithPrivileges();

        if ($data->isEmpty()) {
            $this->info('No data found');
        } else {
            $this->table(
                ['id', 'code', 'name', 'privileges'],
                $data->map(function ($item) {
                    $item->privileges = str_replace(['"', '[', ']'], '', json_encode($item->privileges));
                    $item->privileges = str_replace([','], ', ', $item->privileges);

                    return [
                        $item->tr_id,
                        $item->tr_code,
                        $item->tr_name,
                        str_replace(',', ",\n", str_replace(' ', '', $item->privileges)),
                        // $this->wordWrap($item->privileges, 25)
                    ];
                })
            );
        }
    }

    private function wordWrap($string, $width = 50)
    {
        return wordwrap($string, $width, "\n", true);
    }
}
