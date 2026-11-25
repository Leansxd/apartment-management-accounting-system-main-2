<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gece çalışan otomatik aidat / gecikme zammı ayarları ve silinerek vazgeçilen dönem borçları ("2026-02:15").
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->boolean('dues_auto_run')->default(false);
            $table->boolean('late_fee_auto_run')->default(false);
        });

        Schema::table('dues_definitions', function (Blueprint $table) {
            $table->json('waived_unit_periods')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['dues_auto_run', 'late_fee_auto_run']);
        });

        Schema::table('dues_definitions', function (Blueprint $table) {
            $table->dropColumn('waived_unit_periods');
        });
    }
};
