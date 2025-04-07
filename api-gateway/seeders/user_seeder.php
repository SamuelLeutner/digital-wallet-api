<?php

declare(strict_types=1);

use Faker\Factory;
use Carbon\Carbon;
use App\Model\User;
use Hyperf\DbConnection\Db;
use Hyperf\Database\Seeders\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $faker = Factory::create();
        Db::table('users')->insert([
            [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'cpf_cnpj' => $faker->unique()->numerify('###########'),
                'user_type' => User::USER_TYPE_PF,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => $faker->company,
                'email' => $faker->unique()->companyEmail,
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'cpf_cnpj' => $faker->unique()->numerify('##############'),
                'user_type' => User::USER_TYPE_PJ,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
