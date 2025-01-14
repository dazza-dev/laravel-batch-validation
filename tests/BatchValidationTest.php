<?php

namespace DazzaDev\BatchValidation\Tests;

use Illuminate\Support\Facades\DB;

class BatchValidationTest extends TestCase
{
    /**
     * Test Normal Validation Causes N+1.
     */
    public function test_normal_validation_causes_n_plus_one()
    {
        DB::enableQueryLog();
        $fails = $this->validator->fails();
        $queries = DB::getQueryLog();

        $this->assertTrue($fails);
        $this->assertCount(200, $queries);
    }

    /**
     * Test Validation Solves N+1 Problem.
     */
    public function test_validation_solves_n_plus_one_problem()
    {
        DB::enableQueryLog();
        $this->validator->validateInBatches(10);
        $fails = $this->validator->fails();
        $queries = DB::getQueryLog();

        $this->assertTrue($fails);
        $this->assertCount(20, $queries);
    }
}
