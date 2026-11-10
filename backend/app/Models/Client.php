<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_name', 'contact_person', 'email', 'phone', 'address',
    'description', 'status', 'account_manager_id',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Clients the user may see. Mirrors ClientPolicy::view():
     * admins see all; PMs see clients they account-manage or that have a
     * project they manage; team members see none (they see a project's
     * client name through the project instead).
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (! $user->isProjectManager()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('account_manager_id', $user->id)
                ->orWhereHas('projects', fn (Builder $p) => $p->where('manager_id', $user->id));
        });
    }

    public function isAccountManagedBy(User $user): bool
    {
        return $this->account_manager_id === $user->id;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
