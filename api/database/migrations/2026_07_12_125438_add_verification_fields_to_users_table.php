<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code')->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->boolean('is_verified')->default(false)->after('otp_expires_at');
            $table->string('reset_password_code')->nullable()->after('is_verified');
            $table->timestamp('reset_password_expires_at')->nullable()->after('reset_password_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'otp_code',
                'otp_expires_at',
                'is_verified',
                'reset_password_code',
                'reset_password_expires_at'
            ]);
        });
    }
};
