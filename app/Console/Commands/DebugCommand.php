<?php

namespace App\Console\Commands;

use App\Http\Traits\HospitableTrait;
use Illuminate\Console\Command;

class DebugCommand extends Command
{
    use HospitableTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $properties = $this->getPropertyImages('087367df-18b0-4d62-a29c-7f853e4bf476');

        dd($properties);
    }
}
