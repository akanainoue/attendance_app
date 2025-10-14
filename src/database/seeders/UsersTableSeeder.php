<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create('ja_JP');

        // わかりやすい固定ユーザー
        User::updateOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name'              => 'ユーザー1',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(), // メール認証をスキップしたい場合
            ]
        );

        User::updateOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name'              => 'ユーザー2',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // ランダムユーザーを数名追加
        for ($i = 3; $i <= 10; $i++) {
            User::updateOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name'              => $faker->name,
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
