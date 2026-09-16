<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 12) as $level) {
            Grade::updateOrCreate(
                ['level' => $level],
                [
                    'name' => "Lớp {$level}",
                    'slug' => "lop-{$level}",
                    'sort_order' => $level,
                    'is_active' => true,
                ],
            );
        }
    }
}
