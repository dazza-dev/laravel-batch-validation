<?php

namespace DazzaDev\BatchValidation\Traits;

use Illuminate\Validation\ValidationRuleParser;

trait RulesTrait
{
    /**
     * Extract the database rules.
     */
    public function extractDatabaseRules(array $rules): void
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
}
