<?php

namespace DazzaDev\BatchValidation\Traits;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait DatabaseQueryTrait
{
    /**
     * Count the number of objects in a collection having the given values.
     */
    public function getCount(string $table, string $column, mixed $values, int|string|null $excludeId = null, ?string $idColumn = null, array $extra = [], ?string $connection = null): int
    {
        return $this->buildQuery(
            $table,
            $column,
            $values,
            $excludeId,
            $idColumn,
            $extra,
            $connection,
        )->count();
    }

    /**
     * Retrieves a collection of records from the database that match the specified values.
     */
    public function getCollection(string $table, string $column, mixed $values, int|string|null $excludeId = null, ?string $idColumn = null, array $extra = [], ?string $connection = null): Collection
    {
        return $this->buildQuery(
            $table,
            $column,
            $values,
            $excludeId,
            $idColumn,
            $extra,
            $connection,
        )->get();
    }

    /**
     * Build the query.
     */
    public function buildQuery(string $table, string $column, mixed $values, int|string|null $excludeId = null, ?string $idColumn = null, array $extra = [], ?string $connection = null): Builder
    {
        $query = DB::connection($connection)->table($table)
            ->select($column)
            ->whereIn($column, $values);

        if (! is_null($excludeId) && $excludeId !== 'NULL') {
            $query->where($idColumn ?: 'id', '<>', $excludeId);
        }

        return $this->addConditions($query, $extra);
    }

    /**
     * Add the given conditions to the query.
     */
    protected function addConditions(Builder $query, array $conditions): Builder
    {
        foreach ($conditions as $key => $value) {
            if ($value instanceof Closure) {
                $query->where(function ($query) use ($value) {
                    $value($query);
                });
            } else {
                $this->addWhere($query, $key, $value);
            }
        }

        return $query;
    }

    /**
     * Add a "where" clause to the given query.
     */
    protected function addWhere(Builder $query, string $key, string $extraValue): void
    {
        match (true) {
            $extraValue === 'NULL' => $query->whereNull($key),
            $extraValue === 'NOT_NULL' => $query->whereNotNull($key),
            str_starts_with($extraValue, '!') => $query->where($key, '!=', mb_substr($extraValue, 1)),
            default => $query->where($key, $extraValue),
        };
    }
}
