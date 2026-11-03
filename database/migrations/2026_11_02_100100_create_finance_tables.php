<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Muhasebe: kategoriler, kasalar, borçlar, tahsilat dağılımları, kasa hareketleri,
// yevmiye kayıtları, tedarikçiler, giderler, aidat tanımları ve denetim kayıtları.
// Tüm tutarlar decimal(14,2) saklanır; hesaplar App\Support\Money ile kuruş (tam sayı) üzerinden yapılır.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // income | expense
            $table->timestamps();
        });

        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('cash'); // cash | bank
            $table->string('currency', 3)->default('TRY');
            $table->string('account_number')->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->string('category_name')->default('Genel');
            $table->string('description');
            $table->string('period', 7)->nullable(); // YYYY-MM
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2);
            $table->string('status')->default('pending')->index(); // pending | paid (gecikmiş/kısmi hesaplanır)
            $table->string('distribution_id')->nullable()->index();
            $table->foreignId('dues_definition_id')->nullable();
            // Aynı işin (dönem + tanım + daire) ikinci kez borç üretmesini engeller
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['apartment_id', 'unit_id', 'status']);
            $table->index(['apartment_id', 'due_date']);
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashbox_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // income | expense
            // collection | invoice | transfer_in | transfer_out | advance | pos | general
            $table->string('sub_type')->default('general');
            $table->decimal('amount', 14, 2);
            $table->decimal('applied_to_debts', 14, 2)->default(0);
            $table->decimal('to_advance', 14, 2)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('related_transaction_id')->nullable();
            $table->string('description');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['apartment_id', 'created_at']);
            $table->index(['apartment_id', 'unit_id']);
        });

        // Bir tahsilatın / avans mahsubunun hangi borca ne kadar uygulandığı
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // collection | credit_usage
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        // Çift taraflı yevmiye. Yalnızca eklenir; düzeltme ters kayıtla yapılır.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('reference'); // ör. debt:12, cash_tx:40
            $table->string('entry_type'); // accrual | credit_usage | collection | expense | transfer | general | accrual_reversal
            $table->string('account_name');
            $table->string('account_id')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('description');
            $table->timestamp('created_at')->nullable();
            $table->index(['apartment_id', 'reference']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tax_number')->nullable();
            $table->string('tax_office')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->decimal('amount', 14, 2); // KDV hariç
            $table->unsignedTinyInteger('vat_rate')->default(20);
            $table->decimal('total_amount', 14, 2); // KDV dahil, kasadan çıkan
            $table->string('description');
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('status')->default('pending'); // pending | approved
            $table->foreignId('cashbox_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Aidat / periyodik borç tanımları (eski "aidat motoru" ve "otomatik borçlandırma kuralları" birleşik)
        Schema::create('dues_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->string('calculation')->default('fixed'); // fixed | sqm (m² başına)
            $table->string('target_type')->default('all'); // all | block | unit
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->unsignedTinyInteger('due_day')->default(31); // ayın kaçı (ay kısaysa son gün)
            $table->boolean('is_active')->default(true);
            $table->string('last_run_period', 7)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('module');
            $table->string('reference')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'dues_definitions', 'expenses', 'vendors', 'ledger_entries', 'debt_payments',
            'cash_transactions', 'debts', 'cashboxes', 'transaction_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
