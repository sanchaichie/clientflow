<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Seeds the three fixed roles. Safe to run repeatedly and in production. */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleSlug::cases() as $slug) {
            Role::for($slug);
        }
    }
}
