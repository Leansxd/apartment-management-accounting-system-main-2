<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Banka ekstresi, bütçe, demirbaş, sayaç faturası, bordro, rezervasyon, toplantı, belge arşivi,
// yönetici rolleri ve üye geçmişi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashbox_id')->constrained()->cascadeOnDelete();
            $table->string('batch', 36)->index();
            $table->date('line_date');
            $table->string('description', 500);
            $table->string('reference')->nullable();
            $table->decimal('amount', 14, 2); // + gelen, − giden
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending | posted | ignored
            $table->unsignedBigInteger('cash_transaction_id')->nullable();
            $table->string('hash', 64);
            $table->timestamps();
            $table->unique(['apartment_id', 'hash']); // aynı ekstre ikinci kez içe aktarılırsa satırlar atlanır
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('category_id')->constrained('transaction_categories')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['apartment_id', 'year', 'category_id']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('Demirbaş');
            $table->string('serial_no')->nullable();
            $table->string('location')->nullable();
            $table->date('purchase_date');
            $table->decimal('cost', 14, 2);
            $table->unsignedSmallInteger('useful_life_years')->default(5);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active'); // active | disposed
            $table->date('disposed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('period', 7)->nullable(); // bordro dönemi
            $table->unique(['staff_id', 'period']); // aynı personel için aynı dönem iki kez bordro yazılmaz
        });

        Schema::create('meter_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('meter_type');
            $table->string('method'); // consumption | area | equal
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('total_amount', 14, 2);
            $table->string('description');
            $table->unsignedSmallInteger('units_count');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('common_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->decimal('hourly_fee', 10, 2)->default(0);
            $table->string('open_time', 5)->default('08:00');
            $table->string('close_time', 5)->default('22:00');
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('common_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->string('status')->default('pending'); // pending | approved | rejected | cancelled
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedBigInteger('debt_id')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['common_area_id', 'date']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('olagan'); // olagan | olaganustu | yonetim
            $table->dateTime('meets_at');
            $table->string('location')->nullable();
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->string('status')->default('planned'); // planned | held | cancelled
            $table->timestamps();
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('attendee_name')->nullable();
            $table->boolean('is_proxy')->default(false);
            $table->timestamps();
            $table->unique(['meeting_id', 'unit_id']);
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->unsignedBigInteger('meeting_id')->nullable()->index();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default('Diğer');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('apartment_manager', function (Blueprint $table) {
            $table->string('role')->default('manager'); // manager | accountant | viewer
        });

        Schema::table('members', function (Blueprint $table) {
            $table->date('moved_in_on')->nullable();
            $table->date('moved_out_on')->nullable();
            $table->string('move_out_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['moved_in_on', 'moved_out_on', 'move_out_reason']));
        Schema::table('apartment_manager', fn (Blueprint $t) => $t->dropColumn('role'));
        Schema::dropIfExists('documents');
        Schema::table('decisions', fn (Blueprint $t) => $t->dropColumn('meeting_id'));
        Schema::dropIfExists('meeting_attendances');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('common_areas');
        Schema::dropIfExists('meter_bills');
        Schema::table('expenses', function (Blueprint $t) {
            $t->dropUnique(['staff_id', 'period']);
            $t->dropColumn(['staff_id', 'period']);
        });
        Schema::dropIfExists('assets');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('bank_statement_lines');
    }
};
