<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminGroup = Group::firstOrCreate(
            ['slug' => 'administradores'],
            ['name' => 'Administradores', 'is_admin' => true]
        );

        $helperGroup = Group::firstOrCreate(
            ['slug' => 'ayudantes'],
            ['name' => 'Ayudantes', 'is_admin' => false]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'name' => 'Administrador',
                'password' => 'password',
            ]
        );

        $admin->groups()->syncWithoutDetaching([$adminGroup->id]);

        $helper = User::firstOrCreate(
            ['email' => 'helper@demo.local'],
            [
                'name' => 'Ayudante',
                'password' => 'password',
            ]
        );

        $helper->groups()->syncWithoutDetaching([$helperGroup->id]);

        if (Server::count() === 0) {
            $physical = Server::create([
                'name' => 'Servidor Fisico 1',
                'ip_address' => '10.0.0.10',
                'ram_gb' => 128,
                'storage_gb' => 2048,
                'is_physical' => true,
            ]);

            $physical->groups()->sync([$helperGroup->id]);

            Service::create([
                'server_id' => $physical->id,
                'name' => 'SSH',
                'url' => null,
                'port' => 22,
                'username' => 'root',
                'password' => 'changeme',
            ]);

            Server::create([
                'name' => 'VM Aplicaciones',
                'ip_address' => '10.0.1.20',
                'ram_gb' => 32,
                'storage_gb' => 512,
                'is_physical' => false,
                'parent_id' => $physical->id,
            ]);
        }
    }
}
