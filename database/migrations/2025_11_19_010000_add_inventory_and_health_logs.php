<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('os_name')->nullable()->after('ssh_password');
            $table->string('os_version')->nullable()->after('os_name');
            $table->string('kernel_version')->nullable()->after('os_version');
            $table->unsignedTinyInteger('cpu_cores')->nullable()->after('kernel_version');
            $table->string('owner')->nullable()->after('cpu_cores');
            $table->string('environment')->nullable()->after('owner');
            $table->string('location')->nullable()->after('environment');
            $table->json('critical_services')->nullable()->after('location');
        });

        Schema::create('server_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['up', 'down'])->default('down');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('ssh_connected')->default(false);
            $table->float('ram_usage_percent')->nullable();
            $table->float('cpu_load1')->nullable();
            $table->json('services_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_health_logs');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'os_name',
                'os_version',
                'kernel_version',
                'cpu_cores',
                'owner',
                'environment',
                'location',
                'critical_services',
            ]);
        });
    }
};
