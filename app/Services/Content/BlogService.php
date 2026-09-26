<?php

namespace App\Services\Content;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Blog / Tin tức (PROJECT_PLAN.md, kế hoạch 26/09) — bài giới thiệu + khuyến mãi, admin viết.
 *
 * Nguyên tắc: slug tự sinh và duy nhất (không tin người dùng gõ), ảnh bìa lưu tên tự sinh
 * (không tin tên gốc file upload), xoá ảnh cũ khi thay/khi xoá bài — không để rác trên đĩa.
 */
class BlogService
{
    private const DISK = 'public';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $author, ?UploadedFile $cover = null): BlogPost
    {
        $post = DB::transaction(function () use ($data, $author, $cover) {
            $post = BlogPost::create([
                ...$data,
                'slug' => $this->uniqueSlug($data['title']),
                'cover_path' => $cover ? $this->storeCover($cover) : null,
                'created_by' => $author->id,
                'published_at' => $data['status'] === BlogPost::STATUS_PUBLISHED ? now() : null,
            ]);

            return $post;
        });

        $this->audit->log('blog.created', $post, null, ['title' => $post->title]);

        return $post;
    }

    /** @param  array<string, mixed>  $data */
    public function update(BlogPost $post, array $data, ?UploadedFile $cover = null): BlogPost
    {
        $before = $post->only(['title', 'blog_category_id', 'status']);
        $oldCover = $post->cover_path;

        DB::transaction(function () use ($post, $data, $cover) {
            // Vừa xuất bản lần đầu → đóng dấu thời gian; đã xuất bản từ trước thì giữ nguyên
            // published_at gốc (sửa nội dung không phải xuất bản lại).
            $publishedAt = $post->published_at;
            if ($data['status'] === BlogPost::STATUS_PUBLISHED && ! $post->isPublished()) {
                $publishedAt = now();
            } elseif ($data['status'] === BlogPost::STATUS_DRAFT) {
                $publishedAt = null;
            }

            $post->update([
                ...$data,
                'published_at' => $publishedAt,
                'cover_path' => $cover ? $this->storeCover($cover) : $post->cover_path,
            ]);
        });

        if ($cover && $oldCover) {
            Storage::disk(self::DISK)->delete($oldCover);
        }

        $this->audit->log('blog.updated', $post, $before, $post->refresh()->only(['title', 'blog_category_id', 'status']));

        return $post;
    }

    public function destroy(BlogPost $post): void
    {
        $snapshot = $post->only(['title', 'slug']);
        $cover = $post->cover_path;

        $post->delete();

        if ($cover) {
            Storage::disk(self::DISK)->delete($cover);
        }

        $this->audit->log('blog.deleted', null, $snapshot, null);
    }

    public function createCategory(string $name): BlogCategory
    {
        $category = BlogCategory::create(['name' => $name, 'slug' => $this->uniqueCategorySlug($name)]);
        $this->audit->log('blog.category_created', $category, null, ['name' => $name]);

        return $category;
    }

    public function updateCategory(BlogCategory $category, string $name): BlogCategory
    {
        $before = $category->only('name');
        $category->update(['name' => $name]);
        $this->audit->log('blog.category_updated', $category, $before, ['name' => $name]);

        return $category;
    }

    public function destroyCategory(BlogCategory $category): void
    {
        if ($category->posts()->exists()) {
            throw new RuntimeException('Danh mục còn bài viết — chuyển bài sang danh mục khác trước khi xoá.');
        }

        $snapshot = $category->only('name', 'slug');
        $category->delete();
        $this->audit->log('blog.category_deleted', null, $snapshot, null);
    }

    private function storeCover(UploadedFile $file): string
    {
        return $file->store('blog', self::DISK);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'bai-viet';
        $slug = $base;

        for ($i = 2; BlogPost::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'danh-muc';
        $slug = $base;

        for ($i = 2; BlogCategory::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
