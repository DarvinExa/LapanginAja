<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_token')->nullable()->after('is_verified');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_token');
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'otp_code')) {
                $table->dropColumn('otp_code');
            }
            if (Schema::hasColumn('users', 'otp_expires_at')) {
                $table->dropColumn('otp_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_token', 'email_verification_expires_at']);
        });
    }
};
