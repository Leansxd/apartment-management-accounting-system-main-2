<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Silinerek vazgeçilen gecikme zammı ayları: ana borçta tutulur, bir daha yazılmaz.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->json('waived_late_fee_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('waived_late_fee_months');
        });
    }
};
