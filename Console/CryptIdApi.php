<?php

namespace Modules\IntegratedAPI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CryptIdApi extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'IntegratedAPI:crypt-id-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Encrypted Inbound Source ID for API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sourceName = $this->ask('What is the inbound source name?');
        $query = DB::select("SELECT id FROM integratedapi_inbound_profiles WHERE name = ?", [$sourceName]);
        if (count($query) === 1) {
            $this->line(Crypt::encrypt($query[0]->id));
        } else {
            $this->error('Source not found.');
        }
    }
}