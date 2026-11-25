<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('data');
            $table->string('user_agent')->nullable()->after('ip_address');
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->date('closed_until')->nullable();
            $table->boolean('reminder_auto_run')->default(false);
            $table->boolean('reminder_send_email')->default(false);
            $table->unsignedTinyInteger('reminder_interval_days')->default(7);
            $table->timestamp('reminder_last_run_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['closed_until', 'reminder_auto_run', 'reminder_send_email', 'reminder_interval_days', 'reminder_last_run_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
