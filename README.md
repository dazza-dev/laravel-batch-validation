# Laravel Batch Validation

When performing array validations in Laravel with unique rules, Laravel validates one record at a time, causing an N+1 query problem. This is a common issue when importing data from an Excel file and validating it before inserting it into the database. This package solves the problem by batching the unique validations and using whereIn to prevent the N+1 problem.

## Install

```bash
composer require dazza-dev/laravel-batch-validation
```

## Usage

This package is easy to use. Here's an example of how to apply it:

```php
use Illuminate\Support\Facades\Validator;

$data = [
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
    ['name' => 'User 3', 'email' => 'user3@example.com'],
];

// Validator Instance
$validator = Validator::make($data, [
    '*.name' => 'required',
    '*.email' => 'email:strict|unique:contacts,email',
]);

// Validate in Batches (this prevent n+1 problem)
// You can change the batch value (the default is 10).
$validator->validateInBatches(batchSize: 10);

// Validation fails
if ($validator->fails()) {
    throw new \Exception(json_encode($validator->errors()->messages()));
}
```

## Before and After Optimization

### Before using the package (N+1 problem)

![Before Optimization](https://github.com/user-attachments/assets/569d6e11-014a-4527-a7f1-5817b9f4e4bf)

### After using the package (Optimized)

![After Optimization](https://github.com/user-attachments/assets/dce9f96e-54dd-4d58-8ca3-cf51c6b59371)

## Contributions

Contributions are welcome. If you find any bugs or have ideas for improvements, please open an issue or send a pull request. Make sure to follow the contribution guidelines.

## Author

Laravel Batch Validation was created by [DAZZA](https://github.com/dazza-dev).

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).
