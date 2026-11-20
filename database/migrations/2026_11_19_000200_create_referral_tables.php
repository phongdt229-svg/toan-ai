<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Giới thiệu bạn bè: bạn mới nhận mã giảm cho đơn đầu, người giới thiệu nhận mã khi bạn đó TRẢ TIỀN THẬT.
 *
 * Phần thưởng là MÃ GIẢM GIÁ RIÊNG (vouchers.owner_id) — đi qua đúng VoucherService như mọi mã khác,
 * giữ chỗ/đếm lượt bằng voucher_redemptions; người khác cầm mã cũng không dùng được.
 * Mức thưởng nằm ở referral_settings (Quản trị → Giới thiệu bạn bè), không viết cứng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedTinyInteger('new_user_percent')->default(10);
            $table->unsignedTinyInteger('referrer_percent')->default(20);
            $table->unsignedSmallInteger('voucher_valid_days')->default(60);
            // Chặn một tài khoản "nuôi" hàng trăm tài khoản ảo để gom mã.
            $table->unsignedSmallInteger('max_rewards_per_referrer')->default(20);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 20)->nullable()->unique()->after('remember_token');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            // Mã chỉ một tài khoản được dùng (mã thưởng giới thiệu). NULL = mã thường ai cũng dùng được.
            $table->foreignId('owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | rewarded | void
            $table->foreignId('welcome_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('reward_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->dateTime('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });

        DB::table('referral_settings')->insert([
            'is_enabled' => true, 'new_user_percent' => 10, 'referrer_percent' => 20,
            'voucher_valid_days' => 60, 'max_rewards_per_referrer' => 20,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn('referral_code');
        });
        Schema::dropIfExists('referral_settings');
    }
};
