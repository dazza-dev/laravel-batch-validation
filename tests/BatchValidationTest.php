<?php

namespace DazzaDev\BatchValidation\Tests;

use Illuminate\Support\Facades\Validator;

class BatchValidationTest extends TestCase
{
    /**
     * Test get all languages.
     */
    public function test_it_can_validate_array_in_batches()
    {
        $validator = Validator::make(
            $this->data,
            [
                '*.document_number' => 'unique:contacts,document_number',
                '*.email' => 'unique:contacts,email',
            ]
        );

        $this->assertTrue($validator->fails());
    }
}
