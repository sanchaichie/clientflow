<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Role extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'slug' => RoleSlug::class,
        ];
    }

    /** Find the role row for a slug, creating it if missing (seeders and tests). */
    public static function for(RoleSlug $slug): self
    {
        return static::firstOrCreate(['slug' => $slug], ['name' => $slug->label()]);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
