<?php

namespace App\Http\Middleware;

use App\Support\RolePortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route thuộc một cổng đang ẩn (`portal:parent`): 404, kể cả người giữ link cũ hay gửi POST thẳng. */
class EnsureRolePortalOpen
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(RolePortal::enabled($role), 404);

        return $next($request);
    }
}
