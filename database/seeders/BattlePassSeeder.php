<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BattlePassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('account')->unprepared(
            file_get_contents(__DIR__ . '/_WEB_BattlePass.sql')
        );
    }
}