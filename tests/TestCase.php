<?php

namespace DazzaDev\BatchValidation\Tests;

use DazzaDev\BatchValidation\BatchValidationServiceProvider;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    public array $data;

    public $validator;

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app)
    {
        return [
            BatchValidationServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create Temp Table
        Schema::create('contacts', function ($table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->timestamps();
        });

        // Set Data
        $this->data = $this->loadData();

        // Insert
        $randomIndex = array_rand($this->data);
        DB::table('contacts')->insert([$this->data[$randomIndex]]);

        // Set the validator instance
        $this->validator = Validator::make(
            $this->data,
            [
                '*.document_number' => 'unique:contacts,document_number',
                '*.email' => 'unique:contacts,email',
            ]
        );
    }

    /**
     * Drop Temp Table.
     */
    protected function tearDown(): void
    {
        Schema::dropIfExists('contacts');

        parent::tearDown();
    }

    /**
     * Data.
     */
    protected function loadData(): array
    {
        $faker = Faker::create();
        $data = [];

        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'document_number' => $faker->randomNumber(9),
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
            ];
        }

        return $data;
    }
}
