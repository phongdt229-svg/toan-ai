<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TA-12: "Có vào nhưng không active trong 10 phút → đánh dấu nguy cơ bỏ buổi" — chỉ đánh dấu, không báo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dateTime('idle_flagged_at')->nullable()->after('entered_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropColumn('idle_flagged_at');
        });
    }
};
