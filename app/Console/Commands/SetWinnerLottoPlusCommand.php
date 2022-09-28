<?php

namespace App\Console\Commands;

use App\Models\Animal;
use App\Models\NextResult;
use App\Models\RegisterDetail;
use App\Models\Schedule;
use App\Models\User;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;

class SetWinnerLottoPlusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loko:default';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'obtiene el proximo ganador del sistema';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // horario is_send no enviado  =  0
        // el ultimo que no se a enviado

        $horario = Schedule::where('is_send', 0)->where('sorteo_type_id', 4)->orderBy('id', 'ASC')->first();
        // dd($horario->schedule);
        //obtener socios 
        $socios = User::where('is_socio', 1)->get();


        $so = [];

        for ($h = 0; $h < $socios->count(); $h++) {
            array_push($so, $socios[$h]['id']);
        }

        // array_push($so, auth()->user()->id);

        // dd($so);

        $dt = new DateTime(date('Y-m-d H:i:s'), new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Caracas'));

        // dd($dt);

        $details = RegisterDetail::with(['animal', 'exchange'])
            ->whereIn('admin_id', $so)
            ->where('schedule_id', $horario->id)
            ->where("sorteo_type_id", 4)
            ->where("status", 0)
            ->where("created_at", ">=", $dt->format("Y-m-d") . " 00:00:01")
            // ->where("created_at", "<=", $dt->format("Y-m-d") . " 23:00:00")
            ->get();

        $grupo_animalito = $details->groupBy('animal_id');

        $hh =  $grupo_animalito->map(function ($v, $k) {
            $data = [];
            $data['animal_id'] = $v[0]->animal->id;
            $data['animal'] = $v[0]->animal->nombre;
            $data['animal_numero'] = $v[0]->animal->number;
            $data['total_jugadas'] = $v->count();
            $data['total_monto_usd'] = $v->sum(function ($a) {
                return ($a->monto / $a->exchange->change_usd);
            });
            $data['total_recompensa_usd'] = $v->sum(function ($a) {
                return (($a->monto * 32) / $a->exchange->change_usd);
            });

            // dd($v);
            return $data;
        });

        $selected_animales_ids = [];

        foreach ($hh as $hhh) {
            array_push($selected_animales_ids, $hhh['animal_numero']);
        }


        $complete_animal = Animal::select('id', 'nombre', 'number')->where('sorteo_type_id', 4,)->whereNotIn('number', $selected_animales_ids)->get();

        foreach ($complete_animal as $compl) {
            $data = [];
            $data['animal_id'] = $compl->id;
            $data['animal'] = $compl->nombre;
            $data['animal_numero'] = $compl->number;
            $data['total_jugadas']  = 0;
            $data['total_monto_usd'] = 0;
            $data['total_recompensa_usd'] = 0;
            $hh->push($data);
        }

        $totales = [];
        $totales['total_jugadas'] = $details->count();
        $totales['total_venta_usd'] = 0;
        $totales['total_caja_usd'] = 0;
        $totales['total_comision_usd'] = 0;
        $totales['balance_80'] = 0; // maximo en premios que se repartiran

        foreach ($details as $detail) {
            $totales['total_venta_usd'] += $detail->monto / $detail->exchange->change_usd;
            $totales['total_comision_usd'] += ($detail->monto * 0.12) / $detail->exchange->change_usd;
            $totales['balance_80'] += ($detail->monto * 0.8) / $detail->exchange->change_usd;
            $totales['total_caja_usd'] += ($detail->monto * 0.08) / $detail->exchange->change_usd;
        }

        // dd($totales);
        $hh = $hh->sortByDesc('total_recompensa_usd');

        /**
         * 
         * FILTRAR DEFAULT; RECOGER Y PREMIAR
         */

        /*
        *Default
        */

        $arr_bas = $hh->filter(function ($v, $k) use ($totales) {
            if ($v['total_recompensa_usd'] < $totales['balance_80']) {
                return $v;
            }
        });


        /*
        *Premiar
        */
        $premiar = [];
        $premiar[] = $hh->sortByDesc('total_recompensa_usd')->first();
        $premiar[] = $hh->sortByDesc('total_jugadas')->first();

        /*
        *Recoger
        */
        $recoger = [];
        $recoger[] = $hh->sortBy('total_recompensa_usd')->first();
        $recoger[] = $hh->sortBy('total_jugadas')->first();


        // dd($recoger);
        // dd($arr_premiar);

        $default = $arr_bas->first();

        // dd($arr_premiar);

        $nextR = NextResult::first();

        if ($nextR) {
            $nextR->animal_id = $default['animal_id'];
            $nextR->schedule = $horario['schedule'];
            $nextR->update();
        } else {

            NextResult::create(
                [
                    'animal_id' => $default['animal_id'],
                    'schedule' => $horario['schedule']
                ]
            );
        }


        return 0;
    }
}