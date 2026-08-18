<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            $table->date('business_date')->nullable()->after('number');
            $table->timestamp('closure_requested_at')->nullable()->after('closed_at');
            $table->foreignUuid('closure_requested_by')
                ->nullable()
                ->after('closed_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['tenant_id', 'opened_by', 'business_date'], 'cash_sessions_opener_day_idx');
        });

        $sessions = DB::table('cash_register_sessions')->select('id', 'opened_at')->get();
        foreach ($sessions as $session) {
            $openedAt = (string) $session->opened_at;
            DB::table('cash_register_sessions')->where('id', $session->id)->update([
                'business_date' => substr($openedAt, 0, 10) ?: null,
            ]);
        }

        Schema::create('cash_session_day_unlocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignUuid('unlocked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'business_date'], 'cash_session_day_unlocks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session_day_unlocks');

        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            $table->dropIndex('cash_sessions_opener_day_idx');
            $table->dropConstrainedForeignId('closure_requested_by');
            $table->dropColumn(['business_date', 'closure_requested_at']);
        });
    }
};
