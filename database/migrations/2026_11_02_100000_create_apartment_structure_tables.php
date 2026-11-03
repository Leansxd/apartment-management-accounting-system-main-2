<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Apartman (site), blok, daire, daire grubu ve üyeler (sakinler).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('tax_number')->nullable();
            // Kasa bakiyesi eksiye düşebilir mi (varsayılan: hayır)
            $table->boolean('allow_negative_cashbox')->default(false);
            $table->timestamps();
        });

        // Apartmanı yöneten panel kullanıcıları (sahip ayrıca owner_id ile yetkilidir)
        Schema::create('apartment_manager', function (Blueprint $table) {
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['apartment_id', 'user_id']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->string('unit_no');
            $table->string('floor')->nullable();
            $table->string('usage_type')->default('Konut');
            $table->string('status')->default('Dolu');
            $table->decimal('gross_area', 10, 2)->nullable();
            $table->decimal('land_share', 10, 4)->nullable(); // arsa payı (hazirun cetveli)
            // Dairenin fazla ödemesi / avansı. Yeni borç tahakkukunda otomatik mahsup edilir.
            $table->decimal('credit_balance', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['apartment_id', 'block_id']);
        });

        Schema::create('unit_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('details')->nullable();
            $table->timestamps();
        });

        Schema::create('unit_group_unit', function (Blueprint $table) {
            $table->foreignId('unit_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->primary(['unit_group_id', 'unit_id']);
        });

        // Daire sakini. user_id, sakin davet koduyla mobil hesabını bağladığında dolar.
        // Sakinin hangi daireleri görebileceği doğrudan bu tablodan hesaplanır (ayrı yetki kopyası yok).
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('owner'); // owner | tenant
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('id_number')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('invite_code')->nullable()->unique();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['apartment_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
        Schema::dropIfExists('unit_group_unit');
        Schema::dropIfExists('unit_groups');
        Schema::dropIfExists('units');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('apartment_manager');
        Schema::dropIfExists('apartments');
    }
};
