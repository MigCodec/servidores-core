<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servers', 'ssh_username')) {
            $servers = DB::table('servers')
                ->select('id', 'ip_address', 'ssh_host', 'ssh_port', 'ssh_username', 'ssh_password')
                ->whereNotNull('ssh_username')
                ->get();

            $now = Carbon::now();

            foreach ($servers as $server) {
                if (empty($server->ssh_username) || empty($server->ssh_password)) {
                    continue;
                }

                $exists = DB::table('services')
                    ->where('server_id', $server->id)
                    ->where('is_ssh', true)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('services')->insert([
                    'server_id' => $server->id,
                    'name' => 'SSH',
                    'host' => $server->ssh_host ?: $server->ip_address,
                    'url' => null,
                    'port' => $server->ssh_port ?: 22,
                    'username' => $server->ssh_username,
                    'password' => $server->ssh_password ? Crypt::encryptString($server->ssh_password) : null,
                    'is_ssh' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('servers', function (Blueprint $table) {
            $columns = ['ssh_host', 'ssh_port', 'ssh_username', 'ssh_password'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('servers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (! Schema::hasColumn('servers', 'ssh_host')) {
                $table->string('ssh_host')->nullable()->after('parent_id');
            }
            if (! Schema::hasColumn('servers', 'ssh_port')) {
                $table->unsignedInteger('ssh_port')->nullable()->after('ssh_host');
            }
            if (! Schema::hasColumn('servers', 'ssh_username')) {
                $table->string('ssh_username')->nullable()->after('ssh_port');
            }
            if (! Schema::hasColumn('servers', 'ssh_password')) {
                $table->string('ssh_password')->nullable()->after('ssh_username');
            }
        });
    }
};
