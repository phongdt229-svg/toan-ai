<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Content\BlogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bài viết Blog / Tin tức demo — để trang công khai /tin-tuc và báo cáo ở
 * Quản trị → Bài viết có số liệu thật thay vì trống.
 *
 * Đi qua BlogService thật (đúng luật §29: không update(['status'=>...]) tay) rồi lùi
 * published_at trải đều 30 ngày cho biểu đồ không phẳng. Chỉ chạy ở local/testing.
 * Guard theo bảng blog_posts — chạy lại (php artisan db:seed --class=SampleBlogSeeder)
 * không nhân đôi.
 *
 * Ảnh bìa TỰ VẼ bằng GD (blob + lưới chấm, đúng ngôn ngữ hình ảnh của trang chủ — xem
 * resources/scss/_landing.scss) thay vì tải ảnh ngoài: container không có font tiếng Việt
 * để in chữ lên ảnh, và ảnh stock ngoài có thể dính bản quyền — chữ tiêu đề đã hiện
 * ngay dưới ảnh trong HTML nên không cần in chữ vào ảnh.
 */
class SampleBlogSeeder extends Seeder
{
    private const DISK = 'public';

    /** Cặp màu theo danh mục — cùng bảng màu thương hiệu ở resources/scss/_variables.scss. */
    private const PALETTES = [
        'Giới thiệu' => ['#2563eb', '#0ea5e9'],   // primary/info
        'Khuyến mãi' => ['#f97316', '#f59e0b'],   // accent/warning
        'Sự kiện' => ['#0f172a', '#2563eb'],      // dark/primary
        'Mẹo học tập' => ['#16a34a', '#0ea5e9'],  // success/info
    ];

    public function run(): void
    {
        if (BlogPost::count() > 0) {
            return;
        }

        mt_srand(20260926);

        $admin = User::where('email', 'admin@gmail.com')->first();

        if (! $admin) {
            return;
        }

        $service = app(BlogService::class);
        $categories = $this->createCategories();

        foreach ($this->posts() as $i => $data) {
            $post = $service->create([
                'blog_category_id' => $categories[$data['category']]->id,
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'content' => $data['content'],
                'status' => $data['status'],
            ], $admin);

            $updates = ['cover_path' => $this->generateCover($data['category'], $i)];

            if ($data['status'] === BlogPost::STATUS_PUBLISHED) {
                // Trải đều trong 30 ngày gần đây, mới nhất là bài đầu danh sách.
                $publishedAt = now()->subDays($i * 3 + mt_rand(0, 2))->setTime(mt_rand(8, 20), mt_rand(0, 59));
                $updates += ['published_at' => $publishedAt, 'created_at' => $publishedAt, 'updated_at' => $publishedAt];
            }

            DB::table('blog_posts')->where('id', $post->id)->update($updates);
        }
    }

    /**
     * Ảnh bìa 1200×675 (16:9, khớp CSS `aspect-ratio:16/9` của thẻ bài viết): nền màu đậm của
     * cặp, hai quầng sáng mờ chồng lên nhau (đúng mixin `blob()` dùng ở hero/.pwa-band) + lưới
     * chấm nhẹ (đúng công thức `.cta-band::before`). $seed lệch vị trí quầng sáng cho 10 ảnh
     * không giống hệt nhau dù cùng danh mục.
     */
    private function generateCover(string $category, int $seed): string
    {
        [$colorA, $colorB] = self::PALETTES[$category] ?? self::PALETTES['Giới thiệu'];
        [$w, $h] = [1200, 675];

        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);

        [$r1, $g1, $b1] = $this->hex2rgb($colorA);
        imagefill($im, 0, 0, imagecolorallocate($im, (int) ($r1 * 0.55), (int) ($g1 * 0.55), (int) ($b1 * 0.55)));

        // Lưới chấm mờ trước (nền), quầng sáng vẽ đè lên sau — đúng thứ tự nổi ở .pwa-band.
        $dot = imagecolorallocatealpha($im, 255, 255, 255, 112);
        for ($y = 12; $y < $h; $y += 26) {
            for ($x = 12; $x < $w; $x += 26) {
                imagefilledellipse($im, $x, $y, 3, 3, $dot);
            }
        }

        $this->blob($im, $colorA, (int) (0.72 * $w) + $seed * 17 % 160, (int) (-0.08 * $h), (int) (0.46 * $w));
        $this->blob($im, $colorB, (int) (-0.05 * $w), (int) (1.06 * $h) - $seed * 13 % 140, (int) (0.4 * $w));

        $path = 'blog/cover-'.Str::random(12).'.jpg';
        ob_start();
        imagejpeg($im, null, 82);
        Storage::disk(self::DISK)->put($path, ob_get_clean());
        imagedestroy($im);

        return $path;
    }

    /**
     * Một quầng sáng tròn mờ dần ra ngoài — GD không có radial-gradient thật nên dựng bằng
     * nhiều vòng tròn lồng nhau, alpha đổi rất nhỏ mỗi vòng (nhiều bước) để mắt không thấy
     * "bậc thang". Alpha GD ngược trực giác: 0 = đặc, 127 = trong suốt hẳn — vẽ vòng NGOÀI
     * (gần trong suốt) trước, vòng TRONG (rõ màu hơn) sau, đúng chiều một quầng sáng thật.
     */
    private function blob($im, string $hex, int $cx, int $cy, int $radius): void
    {
        [$r, $g, $b] = $this->hex2rgb($hex);
        $steps = 130;

        for ($i = $steps; $i >= 1; $i--) {
            $ratio = $i / $steps; // 1 ở rìa ngoài cùng → ~0 ở tâm
            // Ease-in: rìa trong suốt kéo dài, đậm dần nhanh hơn khi gần tâm — giống radial-gradient CSS.
            $alpha = 127 - (int) round((1 - $ratio ** 2) * 95);
            $color = imagecolorallocatealpha($im, $r, $g, $b, max(0, min(127, $alpha)));
            $d = (int) ($radius * 2 * $ratio);
            imagefilledellipse($im, $cx, $cy, $d, $d, $color);
        }
    }

    /** @return array{0:int,1:int,2:int} */
    private function hex2rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @return array<string, BlogCategory> theo slug */
    private function createCategories(): array
    {
        $service = app(BlogService::class);
        $names = ['Giới thiệu', 'Khuyến mãi', 'Sự kiện', 'Mẹo học tập'];

        $categories = [];
        foreach ($names as $name) {
            $categories[$name] = $service->createCategory($name);
        }

        return $categories;
    }

    /** @return list<array{category: string, title: string, excerpt: string, content: string, status: string}> */
    private function posts(): array
    {
        $p = fn (string $text) => "<p>{$text}</p>";

        return [
            [
                'category' => 'Giới thiệu', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Chào mừng đến với TOÁN AI',
                'excerpt' => 'Nền tảng học Toán lớp 1–12 cùng AI Tutor — lý thuyết, luyện tập, đề kiểm tra và chấm điểm ngay.',
                'content' => $p('TOÁN AI giúp học sinh học Toán theo đúng chương trình, từ lớp 1 tới lớp 12.')
                    .$p('Mỗi bài học có lý thuyết ngắn gọn, ví dụ minh hoạ, và bài luyện tập chấm điểm ngay.')
                    .$p('AI Tutor không đưa sẵn đáp án — mà giúp học sinh hiểu vì sao mình sai.'),
            ],
            [
                'category' => 'Giới thiệu', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'AI Tutor hoạt động như thế nào?',
                'excerpt' => 'Khi làm sai, AI phân tích lỗi ở bước nào, giải thích lại rồi cho một bài tương tự để luyện đúng chỗ.',
                'content' => $p('Khi học sinh làm sai một câu, AI Tutor không chỉ báo "sai" mà phân tích sai ở bước nào.')
                    .$p('Sau khi giải thích, AI tạo thêm một bài tương tự để học sinh luyện lại đúng chỗ hổng.')
                    .$p('Trong lúc làm bài kiểm tra, AI Tutor bị khoá — để điểm phản ánh đúng sức học.'),
            ],
            [
                'category' => 'Giới thiệu', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Lộ trình học cá nhân hoá là gì?',
                'excerpt' => 'Làm bài kiểm tra đầu vào, hệ thống tự xếp lộ trình 4 giai đoạn theo đúng sức học của từng em.',
                'content' => $p('Sau bài kiểm tra đầu vào, hệ thống xếp lộ trình theo 4 giai đoạn: Nền tảng, Củng cố, Nâng cao, Luyện đề.')
                    .$p('Lộ trình tự điều chỉnh: chủ đề nào đã học mà tụt điểm sẽ tự động có buổi ôn tập thêm.'),
            ],
            [
                'category' => 'Mẹo học tập', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => '5 cách học phân số không bị nhầm',
                'excerpt' => 'Phân số là chủ đề học sinh lớp 6 hay nhầm nhất — vài mẹo nhỏ giúp nhớ lâu hơn.',
                'content' => $p('1. Luôn quy đồng mẫu số trước khi cộng trừ hai phân số khác mẫu.')
                    .$p('2. Vẽ hình chia phần bánh để hình dung phân số lớn nhỏ trực quan hơn.')
                    .$p('3. Kiểm tra lại bằng cách đổi phân số ra số thập phân.')
                    .$p('4. Luyện đều đặn mỗi ngày vài câu thay vì dồn một buổi.')
                    .$p('5. Sai thì hỏi AI Tutor ngay lúc đó — đừng để dồn tới cuối tuần.'),
            ],
            [
                'category' => 'Mẹo học tập', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Phụ huynh nên đồng hành cùng con như thế nào?',
                'excerpt' => 'Không cần giỏi Toán — phụ huynh chỉ cần xem báo cáo tuần và hỏi con học tới đâu.',
                'content' => $p('Phụ huynh liên kết tài khoản với con để xem tiến độ học, điểm số và thời gian học mỗi tuần.')
                    .$p('Báo cáo tuần gửi qua email, tóm tắt chủ đề con đang yếu và bài đã hoàn thành.'),
            ],
            [
                'category' => 'Khuyến mãi', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Ưu đãi khai giảng — giảm 20% gói Pro',
                'excerpt' => 'Áp dụng mã CHAOHE cho gói Pro tháng và Pro năm, giảm tối đa 50.000₫.',
                'content' => $p('Nhân dịp khai giảng năm học mới, TOÁN AI tặng mã giảm giá CHAOHE cho gói Pro.')
                    .$p('Nhập mã ngay ở trang thanh toán, giảm 20% tối đa 50.000₫ cho gói tháng và gói năm.'),
            ],
            [
                'category' => 'Khuyến mãi', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Giảm 50.000₫ cho gói từ 99.000₫',
                'excerpt' => 'Mã GIAM50K áp dụng cho mọi gói từ 99.000₫ trở lên, có hạn tới cuối tháng.',
                'content' => $p('Mã GIAM50K giảm thẳng 50.000₫ cho đơn hàng từ 99.000₫ trở lên.')
                    .$p('Mỗi tài khoản dùng được tối đa 2 lần. Xem chi tiết ở trang Gói học.'),
            ],
            [
                'category' => 'Sự kiện', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Livestream ôn thi học kỳ 1 cùng giáo viên TOÁN AI',
                'excerpt' => 'Buổi ôn tập trực tuyến miễn phí cho học sinh lớp 6–9, giải đáp thắc mắc trực tiếp.',
                'content' => $p('TOÁN AI tổ chức buổi livestream ôn tập học kỳ 1, miễn phí cho mọi học sinh lớp 6 đến lớp 9.')
                    .$p('Giáo viên sẽ giải đề mẫu và trả lời câu hỏi trực tiếp trong buổi live.'),
            ],
            [
                'category' => 'Sự kiện', 'status' => BlogPost::STATUS_PUBLISHED,
                'title' => 'Cuộc thi giải Toán nhanh tháng 10',
                'excerpt' => 'Học sinh làm đề thử thách trong 15 phút, top điểm cao được vinh danh trên hệ thống.',
                'content' => $p('Cuộc thi giải Toán nhanh diễn ra trong tháng 10, mở cho tất cả học sinh đang học tại TOÁN AI.')
                    .$p('Đề thi gồm 15 câu trong 15 phút, top điểm cao nhất mỗi lớp được vinh danh.'),
            ],
            [
                'category' => 'Sự kiện', 'status' => BlogPost::STATUS_DRAFT,
                'title' => 'Tổng kết học kỳ 1 — đang soạn',
                'excerpt' => 'Bản nháp: tổng hợp kết quả học tập toàn hệ thống sau học kỳ 1.',
                'content' => $p('Nội dung đang soạn, chưa xuất bản.'),
            ],
        ];
    }
}
