<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS FR-010 - roles are created and their permissions configured at runtime. */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('name')->get(),
            'catalogue' => Permissions::catalogue(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role,
            'grouped' => $this->groupedPermissions(),
            'assigned' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'grouped' => $this->groupedPermissions(),
            'assigned' => $role->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $this->validated($request, $role);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        // The super administrator keeps unrestricted access by definition.
        if ($role->slug !== Role::SUPER_ADMIN) {
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    /** @return array<string, Collection<int, Permission>> */
    private function groupedPermissions(): array
    {
        return Permission::query()->orderBy('name')->get()->groupBy('group')->all();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);
    }
}
