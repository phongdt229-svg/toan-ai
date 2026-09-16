<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §33 — thu thập dữ liệu đầu vào để AI cá nhân hóa ngay từ lúc đăng ký.
 * `birth_year` được thay bằng `birth_date` vì spec yêu cầu ngày sinh đầy đủ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('grade_id');
            $table->string('address')->nullable()->after('birth_date');
            $table->string('school')->nullable()->after('address');
            // Học lực tự đánh giá lúc đăng ký; sau placement test sẽ có kết quả đo thật.
            $table->enum('self_assessed_level', ['average', 'good', 'excellent'])->nullable()->after('school');
            $table->decimal('math_average_score', 4, 2)->nullable()->after('self_assessed_level');
            // Avatar/giọng của AI Tutor: thầy hoặc cô.
            $table->enum('tutor_persona', ['thay', 'co'])->default('co')->after('math_average_score');
            $table->string('favorite_color', 20)->nullable()->after('tutor_persona');
            $table->json('interests')->nullable()->after('favorite_color');

            $table->dropColumn('birth_year');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('birth_year')->nullable()->after('grade_id');

            $table->dropColumn([
                'birth_date', 'address', 'school', 'self_assessed_level',
                'math_average_score', 'tutor_persona', 'favorite_color', 'interests',
            ]);
        });
    }
};
