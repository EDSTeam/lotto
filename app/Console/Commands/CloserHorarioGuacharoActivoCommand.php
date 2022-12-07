<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;

class CloserHorarioGuacharoActivoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sorteo:closeguacharoactivo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cerrar Guacharo Activo';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $s = Schedule::where('status', 1)->where('sorteo_type_id', 10)->first();
        if (!!$s) {
            $s->status = 0;
            $s->update();
            return $s->schedule . ' ' . 'off';
        } else {
            $sorteos = Schedule::where('sorteo_type_id', 10)->get();
            foreach ($sorteos as $sorteo) {
                $sorteo->status = 1;
                $sorteo->update();
            }
            return 'reset off';
        }

        return 0;
    }
}