<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Nguồn sự thật của RBAC matrix (PROJECT_PLAN.md §5).
 * Chạy lại được nhiều lần: dùng updateOrCreate + sync.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> name => [group, display_name] */
    private const PERMISSIONS = [
        'lesson.view'            => ['lesson', 'Xem bài học'],
        'lesson.create'          => ['lesson', 'Tạo bài học'],
        'lesson.update'          => ['lesson', 'Sửa bài học'],
        'lesson.delete'          => ['lesson', 'Xoá bài học'],
        'lesson.publish'         => ['lesson', 'Xuất bản bài học'],

        'question.view'          => ['question', 'Xem ngân hàng câu hỏi'],
        'question.create'        => ['question', 'Tạo câu hỏi'],
        'question.update'        => ['question', 'Sửa câu hỏi'],
        'question.delete'        => ['question', 'Xoá câu hỏi'],

        'exam.take'              => ['exam', 'Làm đề kiểm tra'],
        'exam.create'            => ['exam', 'Tạo đề kiểm tra'],
        'exam.update'            => ['exam', 'Sửa đề kiểm tra'],
        'exam.delete'            => ['exam', 'Xoá đề kiểm tra'],
        'exam.grade'             => ['exam', 'Chấm bài'],

        'class.manage'           => ['class', 'Quản lý lớp học'],
        'assignment.manage'      => ['assignment', 'Quản lý bài giao'],
        'assignment.submit'      => ['assignment', 'Nộp bài'],

        'progress.view.own'      => ['progress', 'Xem tiến độ của mình'],
        'progress.view.student'  => ['progress', 'Xem tiến độ học sinh'],
        'comment.create'         => ['comment', 'Nhận xét học sinh'],

        'ai.tutor'               => ['ai', 'Dùng AI Tutor'],
        'ai.generate_content'    => ['ai', 'Dùng AI tạo nội dung'],

        'child.link'             => ['parent', 'Liên kết con'],
        'child.view'             => ['parent', 'Xem thông tin con'],

        'subscription.purchase'  => ['subscription', 'Mua gói học'],
        'subscription.manage'    => ['subscription', 'Quản lý gói học'],
        'payment.view.own'       => ['payment', 'Xem lịch sử thanh toán'],
        'payment.manage'         => ['payment', 'Quản lý giao dịch'],

        'user.manage'            => ['system', 'Quản lý người dùng'],
        'teacher.approve'        => ['system', 'Duyệt giáo viên'],
        'package.manage'         => ['system', 'Quản lý gói học'],
        'system.manage'          => ['system', 'Quản trị hệ thống'],
    ];

    /** @var array<string, array{display_name: string, description: string, permissions: list<string>}> */
    private const ROLES = [
        Role::STUDENT => [
            'display_name' => 'Học sinh',
            'description' => 'Học lý thuyết, luyện tập, làm đề, hỏi AI',
            'permissions' => [
                'lesson.view', 'exam.take', 'assignment.submit',
                'progress.view.own', 'ai.tutor',
                'subscription.purchase', 'payment.view.own',
            ],
        ],
        Role::TEACHER => [
            'display_name' => 'Giáo viên',
            'description' => 'Tạo nội dung, quản lý lớp, giao bài, theo dõi học sinh',
            'permissions' => [
                'lesson.view', 'lesson.create', 'lesson.update', 'lesson.delete', 'lesson.publish',
                'question.view', 'question.create', 'question.update', 'question.delete',
                'exam.create', 'exam.update', 'exam.delete', 'exam.grade',
                'class.manage', 'assignment.manage',
                'progress.view.student', 'comment.create',
                'ai.tutor', 'ai.generate_content',
                'subscription.purchase', 'payment.view.own',
            ],
        ],
        Role::PARENT => [
            'display_name' => 'Phụ huynh',
            'description' => 'Theo dõi việc học của con, quản lý gói học',
            'permissions' => [
                'child.link', 'child.view', 'progress.view.student',
                'subscription.purchase', 'subscription.manage', 'payment.view.own',
            ],
        ],
        Role::ADMIN => [
            'display_name' => 'Quản trị viên',
            'description' => 'Toàn quyền hệ thống',
            'permissions' => ['*'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => [$group, $displayName]) {
            Permission::updateOrCreate(
                ['name' => $name],
                ['group' => $group, 'display_name' => $displayName],
            );
        }

        $allPermissionIds = Permission::pluck('id', 'name');

        foreach (self::ROLES as $name => $config) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['display_name' => $config['display_name'], 'description' => $config['description']],
            );

            $ids = $config['permissions'] === ['*']
                ? $allPermissionIds->values()
                : $allPermissionIds->only($config['permissions'])->values();

            $role->permissions()->sync($ids);
        }

        // AppServiceProvider cache danh sách permission để đăng ký Gate.
        Cache::forget('rbac.permission_names');
    }
}
