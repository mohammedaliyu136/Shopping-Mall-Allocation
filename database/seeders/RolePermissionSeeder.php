<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** SRS FR-010 - seeds the permission catalogue and the default role matrix. */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::catalogue() as $slug => [$name, $group]) {
            Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
        }

        foreach (Permissions::matrix() as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            $ids = Permission::query()
                ->whereIn('slug', $definition['permissions'])
                ->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
