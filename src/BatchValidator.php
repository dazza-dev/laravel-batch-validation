<?php

namespace DazzaDev\BatchValidation;

use DazzaDev\BatchValidation\Traits\BatchValidationTrait;
use DazzaDev\BatchValidation\Traits\RulesTrait;
use Illuminate\Validation\Validator;

class BatchValidator extends Validator
{
    use BatchValidationTrait, RulesTrait;

    /**
     * The batch size.
     */
    protected int $batchSize = 10;

    /**
     * Allow use validation in batches.
     */
    protected bool $useBatchValidation = false;

    /**
     * The database rules (unique, exists).
     */
    protected array $databaseRules = [];

    /**
     * The database rules to be applied to the data.
     */
    protected array $databaseRulesExpanded = [];

    /**
     * The array of wildcard attributes with their asterisks expanded.
     */
    protected array $implicitDatabaseAttributes = [];

    /**
     * Enable validation in batches.
     *
     * @return $this
     */
    public function validateInBatches(int $batchSize = 10): self
    {
        $this->batchSize = $batchSize;
        $this->useBatchValidation = true;

        $this->extractDatabaseRules($this->initialRules);

        return $this;
    }

    /**
     * Returns the data which was valid.
     */
    public function valid(): array
    {
        if (! $this->messages) {
            $this->passes();
        }

        //
        if ($this->useBatchValidation) {
            $this->validationInBatches();
        }

        return array_diff_key(
            $this->data,
            $this->attributesThatHaveMessages()
        );
    }

    /**
     * Determine if the data fails the validation rules.
     */
    public function fails(): bool
    {
        $passes = $this->passes();

        //
        if ($this->useBatchValidation) {
            $batches = $this->validationInBatches();

            return ! $passes || ! $batches;
        } else {
            return ! $passes;
        }
    }

    /**
     * Normalize the rules array by replacing dots with placeholders.
     */
    private function normalizeRules(array $rules): array
    {
        return collect($rules)->mapWithKeys(function ($value, $key) {
            return [str_replace('\.', '__dot__'.static::$placeholderHash, $key) => $value];
        })->toArray();
    }
}
