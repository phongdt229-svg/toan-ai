<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Override meta_description do admin nhập ở Quản trị → SEO, xem SeoPageService. */
class SeoPage extends Model
{
    protected $fillable = ['route_name', 'meta_description'];
}
