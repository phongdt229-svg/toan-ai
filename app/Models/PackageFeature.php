<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageFeature extends Model
{
    /** Các khoá tính năng hệ thống đọc — thêm khoá mới phải thêm chỗ kiểm tra tương ứng trong code. */
    public const KEYS = [
        'ai.daily_requests' => 'Số lượt hỏi AI mỗi ngày (giới hạn)',
        'ai.advanced_modes' => 'AI nâng cao: Phân tích lỗi, Bài tương tự (bật/tắt)',
        'practice.daily_questions' => 'Số câu luyện tập mỗi ngày (giới hạn)',
        'reports.advanced' => 'Báo cáo nâng cao cho phụ huynh (bật/tắt)',
    ];

    /** limit: dùng limit_value (null = không giới hạn) · toggle: value '1'/'0'. */
    public const KEY_TYPES = [
        'ai.daily_requests' => 'limit',
        'ai.advanced_modes' => 'toggle',
        'practice.daily_questions' => 'limit',
        'reports.advanced' => 'toggle',
    ];

    /** Dòng chỉ để hiển thị trên bảng giá (không có kiểm tra trong code) dùng tiền tố này. */
    public const DISPLAY_PREFIX = 'display.';

    protected $fillable =['package_id', 'key', 'label', 'value', 'limit_value', 'show_on_pricing', 'sort_order'];

    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'show_on_pricing' => 'boolean',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isEnabled(): bool
    {
        return $this->value === '1';
    }
}
