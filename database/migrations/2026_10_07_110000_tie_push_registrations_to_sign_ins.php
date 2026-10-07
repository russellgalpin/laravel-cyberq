<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push registrations belong to the app sign-in that made them, so signing out
 * (revoking the token) stops alerts and Live Activities reaching that phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['push_devices', 'live_activity_tokens'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('personal_access_token_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            });
        }
    }
};
