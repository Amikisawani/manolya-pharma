<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->longText('ocr_text')->nullable()->after('ocr_status');
            $table->string('ocr_engine')->nullable()->after('ocr_text');
            $table->text('ocr_error')->nullable()->after('ocr_engine');
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_error');
        });

        // PostgreSQL full-text helper index (simple config = accents/lang agnostic)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                CREATE INDEX IF NOT EXISTS documents_search_fts_idx
                ON documents
                USING GIN (
                    to_tsvector(
                        'simple',
                        coalesce(title, '') || ' ' || coalesce(type, '') || ' ' || coalesce(search_text, '')
                    )
                )
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS documents_search_fts_idx');
        }

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['ocr_text', 'ocr_engine', 'ocr_error', 'ocr_processed_at']);
        });
    }
};
