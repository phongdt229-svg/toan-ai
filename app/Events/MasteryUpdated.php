<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Mức nắm vững chủ đề của học sinh vừa được tính lại (sau luyện tập, bài tập, đề). */
class MasteryUpdated
{
    use Dispatchable;

    public function __construct(public readonly User $student) {}
}
