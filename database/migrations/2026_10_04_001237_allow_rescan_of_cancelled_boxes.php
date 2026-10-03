<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cancelled inbound scan soft deletes the box and frees its sticker for a new scan (PRD 5.9).
     * The QR code stays unique at database level, but only among boxes that are not cancelled:
     * active_qr_code is NULL for soft deleted rows, and NULLs never collide in a unique index.
     */
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->index('qr_label_id');
            $table->index('qr_code');
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropUnique(['qr_label_id']);
            $table->dropUnique(['qr_code']);
            $table->string('active_qr_code')->nullable()->virtualAs('case when deleted_at is null then qr_code end')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropUnique(['active_qr_code']);
            $table->dropColumn('active_qr_code');
            $table->unique('qr_label_id');
            $table->unique('qr_code');
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropIndex(['qr_label_id']);
            $table->dropIndex(['qr_code']);
        });
    }
};
