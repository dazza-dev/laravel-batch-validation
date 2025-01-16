<?php

namespace DazzaDev\BatchValidation\Traits;

use Illuminate\Support\LazyCollection;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationRuleParser;

trait BatchValidationTrait
{
    use DatabaseQueryTrait;

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
     * Get the values of a given attribute.
     */
    public function getValues(string $attribute, array $data): array
    {
        return array_column($data, $attribute);
    }
}
