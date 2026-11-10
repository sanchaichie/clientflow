<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;

abstract class Controller
{
    /**
     * Apply ?sort=column or ?sort=-column (descending), accepting only
     * allow-listed columns so users cannot sort by arbitrary SQL.
     *
     * @param  list<string>  $allowed
     */
    protected function applySort(Builder $query, ?string $sort, array $allowed, string $default): Builder
    {
        $sort = $sort ?: $default;
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = ltrim($default, '-');
            $sort = $default;
        }

        return $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy($query->getModel()->getQualifiedKeyName()); // stable pagination
    }
}
