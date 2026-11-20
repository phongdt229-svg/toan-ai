<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Số điểm của mỗi loại hoạt động — quản trị chỉnh ở Quản trị → Điểm thưởng. */
class RewardRule extends Model
{
    protected $fillable = ['points', 'is_active'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'is_active' => 'boolean'];
    }
}
