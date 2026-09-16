<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 12 không kèm sẵn trait này; project dùng $this->authorize() trong controller.
    use AuthorizesRequests;
}
