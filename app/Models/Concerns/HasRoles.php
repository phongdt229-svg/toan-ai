<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @param  string|array<int, string>  $roles  slug hoặc mảng slug
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roleNames()->intersect($roles)->isNotEmpty();
    }

    public function isStudent(): bool
    {
        return $this->hasRole(Role::STUDENT);
    }

    public function isTeacher(): bool
    {
        return $this->hasRole(Role::TEACHER);
    }

    public function isParent(): bool
    {
        return $this->hasRole(Role::PARENT);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissionNames()->contains($permission);
    }

    /** @return Collection<int, string> */
    public function roleNames(): Collection
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('name')
            : $this->roles()->pluck('name');
    }

    /**
     * Quyền của user = hợp của quyền tất cả role. Cache trong request để tránh query lặp.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        return once(fn () => Permission::query()
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->whereIn('permission_role.role_id', $this->roles()->pluck('roles.id'))
            ->distinct()
            ->pluck('permissions.name'));
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->unsetRelation('roles');
    }
}
