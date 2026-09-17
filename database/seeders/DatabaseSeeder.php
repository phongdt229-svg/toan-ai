<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            GradeSeeder::class,
            PackageSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call([
                DemoUserSeeder::class,
                SampleCurriculumSeeder::class,
                SampleQuestionSeeder::class,
                SamplePathSeeder::class,
                SampleExamSeeder::class,
                SampleClassSeeder::class,
            ]);
        }
    }
}
