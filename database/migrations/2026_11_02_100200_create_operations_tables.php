<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Operasyon ve iletişim: görevler, personel, stok, sayaçlar, güvenlik kayıtları,
// destek talepleri, duyurular, anketler, karar defteri, bildirimler ve emlak ilanları.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->default('Güvenlik');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('salary', 14, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->string('status')->default('Aktif');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('priority')->default('medium'); // low | medium | high
            $table->string('category')->default('maintenance');
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending'); // pending | in_progress | completed
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('Temizlik');
            $table->integer('quantity')->default(0);
            $table->string('unit')->default('Adet');
            $table->integer('min_stock')->default(5);
            $table->string('location')->default('Ana Depo');
            $table->timestamps();
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete(); // null = ortak alan
            $table->string('name');
            $table->string('type')->default('Elektrik');
            $table->string('serial_no')->nullable();
            $table->decimal('initial_value', 14, 3)->default(0);
            $table->decimal('last_value', 14, 3)->default(0);
            $table->string('status')->default('Aktif');
            $table->timestamps();
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 14, 3);
            $table->decimal('consumption', 14, 3)->default(0);
            $table->date('read_on');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // Kargo, otopark ve ziyaretçi kayıtları tek tabloda (kind ile ayrılır)
        Schema::create('security_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // cargo | parking | visitor
            $table->string('unit_no')->nullable();
            $table->string('full_name')->nullable();
            $table->string('company')->nullable();
            $table->string('tracking_no')->nullable();
            $table->string('plate')->nullable();
            $table->string('note')->nullable();
            $table->string('status'); // cargo: beklemede|teslim-edildi|iptal, parking/visitor: aktif|ayrildi
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['apartment_id', 'kind']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // mobilden açan sakin
            $table->string('unit_no')->nullable();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category')->default('Genel');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open'); // open | in_progress | resolved | closed
            $table->text('response')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('priority')->default('normal'); // normal | high
            $table->date('expires_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->date('due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('text');
        });

        // Daire başına tek oy
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['poll_id', 'unit_id']);
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('decision_no');
            $table->date('decided_on');
            $table->string('subject');
            $table->text('content');
            $table->string('attendees')->nullable();
            $table->timestamps();
        });

        // Her bildirim tek hedeflidir: tüm site ya da tek bir daire
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete(); // null = tüm site
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('info');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['apartment_id', 'unit_id']);
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['notification_id', 'user_id']);
        });

        // Emlak portföyü (satılık/kiralık ilanlar)
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('price', 14, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('category')->default('Apartment');
            $table->string('status')->default('active'); // active | sold | passive
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('video_url')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('agent_phone')->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->unsignedSmallInteger('balconies')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->string('floor')->nullable();
            $table->unsignedSmallInteger('total_floors')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->boolean('is_furnished')->default(false);
            $table->boolean('is_citizenship')->default(false);
            $table->boolean('is_residence_permit')->default(false);
            $table->boolean('is_credit_eligible')->default(true);
            $table->json('distances')->nullable(); // {"Deniz": "500 m", ...}
            $table->json('features')->nullable();
            $table->json('facades')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['properties', 'notification_reads', 'notifications', 'decisions', 'poll_votes', 'poll_options',
            'polls', 'announcements', 'tickets', 'security_entries', 'meter_readings', 'meters',
            'inventory_items', 'tasks', 'staff'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
