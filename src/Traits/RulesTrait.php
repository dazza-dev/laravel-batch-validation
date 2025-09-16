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
        $this->initialRules = $this->normalizeRules($rules);
        $this->rules = [];

        [$uniqueAndExistsRules, $otherRules] = $this->separateRules($this->initialRules);

        $this->addRules($otherRules);
        $this->addDatabaseRules($uniqueAndExistsRules);
    }

    /**
     * Separate unique and exists rules from other rules.
     */
    private function separateRules(array $rules): array
    {
        $uniqueAndExistsRules = [];
        $otherRules = [];

        foreach ($rules as $key => $rule) {
            $ruleParts = is_string($rule) ? explode('|', $rule) : $rule;

            $uniqueExists = array_filter($ruleParts, fn ($part) => preg_match('/\b(unique|exists)\b/', $part));
            $others = array_diff($ruleParts, $uniqueExists);

            if (! empty($uniqueExists)) {
                $uniqueAndExistsRules[$key] = $uniqueExists;
            }

            if (! empty($others)) {
                $otherRules[$key] = is_string($rule) ? implode('|', $others) : $others;
            }
        }

        return [$uniqueAndExistsRules, $otherRules];
    }

    /**
     * Parse the given rules and merge them into database rules.
     */
    public function addDatabaseRules(array $rules): void
    {
        $parser = new ValidationRuleParser($this->data);
        $response = $parser->explode(
            ValidationRuleParser::filterConditionalRules($rules, $this->data)
        );

        $this->databaseRules = $rules;
        $this->databaseRulesExpanded = $response->rules;
        $this->implicitDatabaseAttributes = $response->implicitAttributes;
    }
}
