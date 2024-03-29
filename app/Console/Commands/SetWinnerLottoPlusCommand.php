<?php

namespace App\Console\Commands;

use App\Http\Libs\Telegram;
use App\Http\Libs\Wachiman;
use App\Models\Animal;
use App\Models\LottoPlusConfig;
use App\Models\NextResult;
use App\Models\RegisterDetail;
use App\Models\Result;
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
    public $totales = [];
    public $complete_animal;

    public function handle()
    {
        $telegram = new Telegram();
        // horario is_send no enviado  =  0
        // el ultimo que no se a enviado
        $restric = 20;
        $restric2 = 10;

        $setting = LottoPlusConfig::first();

        $horario = Schedule::where('is_send', 0)->where('sorteo_type_id', 4)->orderBy('id', 'ASC')->first();

        $animalesAnteriores = Result::where('sorteo_type_id', 4)->orderBy('id', 'ASC')->limit($restric)->get();
        $animalesAnteriores2 = Result::where('sorteo_type_id', 4)->orderBy('id', 'ASC')->limit($restric2)->get();

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

        // (dt);

        $details = RegisterDetail::with(['animal', 'exchange'])
            ->whereIn('admin_id', $so)
            ->where('schedule_id', $horario->id)
            ->where("sorteo_type_id", 4)
            ->where("status", 0)
            ->where("created_at", ">=", $dt->format("Y-m-d") . " 00:00:01")
            // ->where("created_at", "<=", $dt->format("Y-m-d") . " 23:00:00")
            ->get();

        // pasar details


        $hh = $this->gethh($details, $setting);

        /*
         *Eliminar del array los numeros que ya hayan salido
         */

        //  dd($hh);

        $hhNoRepets =  $hh->filter(function ($v, $k) use ($animalesAnteriores) {
            // dd($v['animal_id'], $animalesAnteriores->pluck('animal_id'));
            if (!in_array($v['animal_id'], $animalesAnteriores->pluck('animal_id')->toArray(), true)) {
                // dd($v);
                return $v;
            }
        });

        $hhNoRepets2 =  $hh->filter(function ($v, $k) use ($animalesAnteriores2) {
            // dd($v['animal_id'], $animalesAnteriores->pluck('animal_id'));
            if (!in_array($v['animal_id'], $animalesAnteriores2->pluck('animal_id')->toArray(), true)) {
                // dd($v);
                return $v;
            }
        });

        // $hhReperts =  $hh->filter(function ($v, $k) use ($animalesAnteriores) {
        //     //  dd($v,$animalesAnteriores->pluck('animal_id'));
        //     if (in_array($v['animal_id'], $animalesAnteriores->pluck('animal_id')->toArray(), true)) {
        //         // dd($v);
        //         return $v;
        //     }
        // });

        $totales = $this->totales;
        
        if ($hhNoRepets->count() != 0) {
            $arr_bas = $hhNoRepets->filter(function ($v, $k) use ($totales) {
                if ($v['total_recompensa_usd'] < $totales['balance_80']) {
                    return $v;
                }
            });
            $telegram->sendMessage('$hhNoRepets de conteo 20');
        } else {
            $arr_bas = $hhNoRepets2->filter(function ($v, $k) use ($totales) {
                if ($v['total_recompensa_usd'] < $totales['balance_80']) {
                    return $v;
                }
            });
            $telegram->sendMessage('$hhNoRepets de conteo 10');
        }




        // /*
        // *Premiar
        // */

        // $premiar = [];
        // $premiar[] = $hh->sortByDesc('total_recompensa_usd')->first();
        // $premiar[] = $hh->sortByDesc('total_jugadas')->first();


        // dd($arr_bas);
        // error_log($hh);
        // error_log(json_encode($premiar));

        /*
        *Recoger
        */

        // $recoger = [];
        // $recoger[] = $hh->sortBy('total_recompensa_usd')->first();
        // $recoger[] = $hh->sortBy('total_jugadas')->first();


        $first = $arr_bas->first();

        if ($first == null) {
            $win = $this->complete_animal[rand(0, 38)];
            $default = [];
            $default['animal_id'] = $win->id;
        } else {
            $default = $first;
        }

        //activar modo automatico para dias no laborables

        if(true){
            $win = $this->complete_animal[rand(0, 38)];
            $default = [];
            $default['animal_id'] = $win->id;
        }



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


        // $telegram->sendMessage('✅ Lotto Plus ganador auto-seteado para las ' . $horario['schedule'] . ' ' . $first['animal'] . ' ' . $first['animal_numero']);
        $telegram->sendMessage('https://lotto.fivipsystem.com/lottoloko');


        // dd($default['animal_id'], $animalesAnteriores->pluck('animal_id')->toArray());

        if (in_array($default['animal_id'], $animalesAnteriores->pluck('animal_id')->toArray(), true)) {
            $w = new Wachiman();
            $w->sendMessage("🆘 Resultado de Lotto Plus se va a repetir 🆘");
            $w->sendMessage("https://lotto.fivipsystem.com");
            $w->sendMessage("Ingresa para modificar");
        }


        return 0;
    }

    public function gethh($details, $setting): object
    {
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

        // dd($hh);

        $selected_animales_ids = [];

        foreach ($hh as $hhh) {
            array_push($selected_animales_ids, $hhh['animal_numero']);
        }


        $complete_animal = Animal::select('id', 'nombre', 'number')->where('sorteo_type_id', 4,)->whereNotIn('number', $selected_animales_ids)->get();
        $this->complete_animal = $complete_animal;
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

        $this->totales = [];
        $this->totales['total_jugadas'] = $details->count();
        $this->totales['total_venta_usd'] = 0;
        $this->totales['total_caja_usd'] = 0;
        $this->totales['total_comision_usd'] = 0;
        $this->totales['balance_80'] = 0; // maximo en premios que se repartiran

        foreach ($details as $detail) {
            $this->totales['total_venta_usd'] += $detail->monto / $detail->exchange->change_usd;
            $this->totales['total_comision_usd'] += ($detail->monto *  $setting->porcent_comision) / $detail->exchange->change_usd;
            $this->totales['balance_80'] += ($detail->monto * $setting->porcent_limit) / $detail->exchange->change_usd;
            $this->totales['total_caja_usd'] += ($detail->monto * $setting->porcent_cash) / $detail->exchange->change_usd;
        }

        // dd($totales);
        $hh = $hh->sortBy(
            [
                ['total_jugadas', 'desc'],
                ['total_recompensa_usd', 'desc'],
            ]
        );
        return $hh;
    }
}
