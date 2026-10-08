<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Giải bài từ ảnh (đặc tả module 7): chế độ AI `read_image` + giới hạn `ai.image_daily` theo gói.
 *
 * PackageSeeder không thêm quyền lợi vào gói đã tồn tại (để không đè chỉnh sửa của admin), nên gói đang chạy
 * được bổ sung dòng `ai.image_daily` tại đây — chỉ khi gói đó chưa có.
 */
return new class extends Migration
{
    private const LIMITS = ['free' => 3, 'pro' => 20, 'premium' => 50];

    public function up(): void
    {
        DB::statement("ALTER TABLE ai_conversations MODIFY mode ENUM('chat','hint','explain','check_answer','similar_exercise','analyze_mistake','read_image') NOT NULL");

        foreach (DB::table('packages')->get(['id', 'tier']) as $package) {
            $limit = self::LIMITS[$package->tier] ?? null;

            if ($limit === null || DB::table('package_features')->where('package_id', $package->id)->where('key', 'ai.image_daily')->exists()) {
                continue;
            }

            DB::table('package_features')->insert([
                'package_id' => $package->id,
                'key' => 'ai.image_daily',
                'label' => "Chụp ảnh đề: {$limit} lần mỗi ngày",
                'value' => '1',
                'limit_value' => $limit,
                'show_on_pricing' => true,
                'sort_order' => (int) DB::table('package_features')->where('package_id', $package->id)->max('sort_order') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ai_conversations')->where('mode', 'read_image')->delete();
        DB::statement("ALTER TABLE ai_conversations MODIFY mode ENUM('chat','hint','explain','check_answer','similar_exercise','analyze_mistake') NOT NULL");
        DB::table('package_features')->where('key', 'ai.image_daily')->delete();
    }
};
