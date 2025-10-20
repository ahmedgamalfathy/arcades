<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Setting\Param\Param;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ParamSeeder extends Seeder
{


    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('parameters')->insert([
            [
                'name'=>'timeWarning',
                'order'=>1,
                'created_at'=>Carbon::now(),
                'updated_at'=>Carbon::now(),
            ],
            [
                'name'=>'voiceWarning',
                'order'=>2,
                'created_at'=>Carbon::now(),
                'updated_at'=>Carbon::now(),
            ]
       ]);
        $params = [
            ['type' => '5', 'note' =>'5 minutes','parameter_order'=>1],        
        ];
        foreach ($params as $param) {
            Param::create($param);
        }
    }
}
