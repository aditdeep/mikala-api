<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('mitra', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra', 'payment_type'))
                $table->enum('payment_type', ['cash', 'kredit'])->nullable()->after('bank_account_name');
            if (!Schema::hasColumn('mitra', 'contract_agreed_at'))
                $table->timestamp('contract_agreed_at')->nullable()->after('payment_type');
            if (!Schema::hasColumn('mitra', 'contract_ip_address'))
                $table->string('contract_ip_address', 45)->nullable()->after('contract_agreed_at');
            if (!Schema::hasColumn('mitra', 'status_rekrutmen'))
                $table->enum('status_rekrutmen', ['pending','in_review','verified','rejected'])->default('pending')->after('contract_ip_address');
            if (!Schema::hasColumn('mitra', 'price_rate'))
                $table->decimal('price_rate', 10, 2)->nullable()->after('status_rekrutmen');
            if (!Schema::hasColumn('mitra', 'catatan_rekrutmen'))
                $table->text('catatan_rekrutmen')->nullable()->after('price_rate');
            if (!Schema::hasColumn('mitra', 'verified_at'))
                $table->timestamp('verified_at')->nullable()->after('catatan_rekrutmen');
            if (!Schema::hasColumn('mitra', 'verified_by'))
                $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
        });
    }
    public function down(): void {
        Schema::table('mitra', function (Blueprint $table) {
            $table->dropColumn(['payment_type','contract_agreed_at','contract_ip_address',
                'status_rekrutmen','price_rate','catatan_rekrutmen','verified_at','verified_by']);
        });
    }
};
