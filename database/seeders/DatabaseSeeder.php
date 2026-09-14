<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);


        if (config('global.server.version') === 'vSRO') {
            $this->call(LogInstanceWorldInfo::class);
            $this->call(LogChatMessage::class);
            $this->call(ItemNameSeeder::class);
        }

        $this->call(SettingSeeder::class);
        $this->call(BattlePassSeeder::class);

        //$this->call(MagOptSeeder::class);
        //$this->call(ReferralSeeder::class);
    }
}
