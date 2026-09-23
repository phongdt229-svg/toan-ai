<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chống nhắc trùng hạn bài giao: một học sinh một bài chỉ nhắc một lần. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_students', function (Blueprint $table) {
            $table->dateTime('due_reminded_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_students', function (Blueprint $table) {
            $table->dropColumn('due_reminded_at');
        });
    }
};
