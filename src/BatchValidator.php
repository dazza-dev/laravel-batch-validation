<?php

namespace DazzaDev\BatchValidation;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;

class BatchValidator extends Validator
{
    /**
     * The batch size.
     */
    public int $batchSize = 10;

    /**
     * The database rules (unique, exists).
     */
    protected array $databaseRules;

    /**
     * The database rules to be applied to the data.
     */
    protected array $databaseRulesExpanded;

    /**
     * The array of wildcard attributes with their asterisks expanded.
     */
    protected array $implicitDatabaseAttributes;

    /**
     * Set the validation rules.
     *
     * @return $this
     */
    public function setRules(array $rules)
    {
        $rules = collect($rules)->mapWithKeys(function ($value, $key) {
            return [str_replace('\.', $this->dotPlaceholder, $key) => $value];
        })->toArray();

        $this->initialRules = $rules;

        $this->rules = [];

        // Separate Rules
        $uniqueAndExistsRules = [];
        $otherRules = [];
        foreach ($rules as $key => $rule) {
            $ruleParts = explode('|', $rule);
            $filteredParts = array_filter($ruleParts, function ($part) use ($key, &$uniqueAndExistsRules) {
                if (preg_match('/\b(unique|exists)\b/', $part)) {
                    $uniqueAndExistsRules[$key][] = $part;

                    return false;
                }

                return true;
            });

            if (! empty($filteredParts)) {
                $otherRules[$key] = implode('|', $filteredParts);
            }
        }

        $this->addRules($otherRules);
        $this->addDatabaseRules($uniqueAndExistsRules);

        return $this;
    }

    /**
     * Parse the given rules and merge them into database rules.
     */
    public function addDatabaseRules(array $rules): void
    {
        // The primary purpose of this parser is to expand any "*" rules to the all
        // of the explicit rules needed for the given data. For example the rule
        // names.* would get expanded to names.0, names.1, etc. for this data.
        $response = (new ValidationRuleParser($this->data))
            ->explode(ValidationRuleParser::filterConditionalRules($rules, $this->data));

        $this->databaseRules = $rules;
        $this->databaseRulesExpanded = $response->rules;
        $this->implicitDatabaseAttributes = $response->implicitAttributes;
    }

    /**
     * Validation In Batches.
     */
    public function validationInBatches(): bool
    {
        $this->messages = $this->messages ?: new MessageBag;

        LazyCollection::make($this->data)
            ->chunk($this->batchSize)
            ->each(function ($batch) {
                $this->validateBatch($batch->toArray());
            });

        return $this->messages->isEmpty();
    }

    /**
     * Validate Batch.
     */
    public function validateBatch(array $batch)
    {
        foreach ($this->databaseRules as $attribute => $rules) {
            if ($this->shouldBeExcluded($attribute)) {
                $this->removeAttribute($attribute);

                continue;
            }

            if ($this->stopOnFirstFailure && $this->messages->isNotEmpty()) {
                break;
            }

            foreach ($rules as $rule) {
                $this->validateDatabaseAttribute($attribute, $rule, $batch);

                if ($this->shouldBeExcluded($attribute)) {
                    break;
                }

                if ($this->shouldStopValidating($attribute)) {
                    break;
                }
            }
        }

        return $this->messages->isEmpty();
    }

    /**
     * Validate a given attribute against a rule.
     */
    protected function validateDatabaseAttribute(string $attribute, string $rule, array $data): void
    {
        $this->currentRule = $rule;

        [$rule, $parameters] = ValidationRuleParser::parse($rule);

        if ($rule === '') {
            return;
        }

        // First we will get the correct keys for the given attribute in case the field is nested in
        // an array. Then we determine if the given rule accepts other field names as parameters.
        // If so, we will replace any asterisks found in the parameters with the correct keys.
        if ($this->dependsOnOtherFields($rule)) {
            $parameters = $this->replaceDotInParameters($parameters);

            if ($keys = $this->getExplicitKeys($attribute)) {
                $parameters = $this->replaceAsterisksInParameters($parameters, $keys);
            }
        }

        $method = "validateBatch{$rule}";
        $attributeKey = str_replace('*.', '', $attribute);

        // Get data
        $values = $this->getValues($attributeKey, $data);

        // Validate
        $foundValues = $this->$method($attributeKey, $values, $parameters, $this);
        if (count($foundValues) > 0) {
            foreach ($foundValues as $value) {
                $index = array_search($value, array_column($this->data, $attributeKey));
                $attributeName = "{$index}.{$attributeKey}";
                $this->addFailure($attributeName, $rule, $parameters);
            }
        }
    }

    /**
     * Get the values of a given attribute.
     */
    public function getValues(string $attribute, array $data): array
    {
        return array_column($data, $attribute);
    }

    /**
     * Determine if the data fails the validation rules.
     */
    public function fails(): bool
    {
        $passes = $this->passes();
        $batches = $this->validationInBatches();

        return ! $passes || ! $batches;
    }

    /**
     * Validate Unique values.
     */
    public function validateBatchUnique(string $attribute, array $values, array $parameters): array
    {
        $this->requireParameterCount(1, $parameters, 'unique');

        [$connection, $table, $idColumn] = $this->parseTable($parameters[0]);

        // The second parameter position holds the name of the column that needs to
        // be verified as unique. If this parameter isn't specified we will just
        // assume that this column to be verified shares the attribute's name.
        $column = $this->getQueryColumn($parameters, $attribute);

        $id = null;

        if (isset($parameters[2])) {
            [$idColumn, $id] = $this->getUniqueIds($idColumn, $parameters);

            if (! is_null($id)) {
                $id = stripslashes($id);
            }
        }

        // Extra parameters
        $extra = $this->getUniqueExtra($parameters);

        if ($this->currentRule instanceof Unique) {
            $extra = array_merge($extra, $this->currentRule->queryCallbacks());
        }

        // Get Collection
        $collection = $this->getCollection(
            $table,
            $column,
            $values,
            $id,
            $idColumn,
            $extra,
            $connection
        );

        return $collection->pluck($column)->toArray();
    }

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
        if ($extraValue === 'NULL') {
            $query->whereNull($key);
        } elseif ($extraValue === 'NOT_NULL') {
            $query->whereNotNull($key);
        } elseif (str_starts_with($extraValue, '!')) {
            $query->where($key, '!=', mb_substr($extraValue, 1));
        } else {
            $query->where($key, $extraValue);
        }
    }
}
