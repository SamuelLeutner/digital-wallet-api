<?php

declare(strict_types=1);

use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Database\Seeders\Seeder;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $userIds = Db::table('users')->pluck('id')->toArray();
        Db::table('wallets')->insert([
            [
                'user_id' => $userIds[0],
                'balance' => 100.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => $userIds[1],
                'balance' => 100.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
