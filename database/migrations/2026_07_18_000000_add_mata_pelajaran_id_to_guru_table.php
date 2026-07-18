<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->after('status_kepegawaian')
                ->constrained('mata_pelajaran')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mata_pelajaran_id');
        });
    }
};
