<?php

namespace DazzaDev\BatchValidation;

use DazzaDev\BatchValidation\Traits\BatchValidationTrait;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;

class BatchValidator extends Validator
{
    use BatchValidationTrait;

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
     * Determine if the data fails the validation rules.
     */
    public function fails(): bool
    {
        $passes = $this->passes();
        $batches = $this->validationInBatches();

        return ! $passes || ! $batches;
    }
}
