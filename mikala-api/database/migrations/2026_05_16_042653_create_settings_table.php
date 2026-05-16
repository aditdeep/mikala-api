<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Seed default values
        DB::table('settings')->insert([
            // Rekening perusahaan
            ['key' => 'bank_name',        'value' => 'BCA',                      'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bank_account',     'value' => '1234567890',               'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bank_account_name','value' => 'PT Mikala Global Medika',  'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            // Xendit
            ['key' => 'xendit_enabled',   'value' => 'false',                    'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'xendit_secret_key','value' => '',                         'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'xendit_public_key','value' => '',                         'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
