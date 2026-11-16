<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Nội dung chính sách admin tự soạn, xem LegalPageService. */
class LegalPage extends Model
{
    protected $fillable = ['slug', 'content'];

    /** Lọc lúc lưu như Lesson/Blog; riêng trang này cho thêm mailto:/tel: vì mục Liên hệ cần. */
    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $value) => (new HtmlSanitizer(['mailto', 'tel']))->clean($value));
    }
}
