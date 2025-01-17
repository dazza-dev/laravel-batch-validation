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
$validator->validateInBatches();

// Validation fails
if ($validator->fails()) {
    throw new \Exception(json_encode($validator->errors()->messages()));
}
```

## Batch Size

You can change the batch size by passing the `batchSize` parameter to the `validateInBatches` method. The default batch size is 10.

```php
$validator->validateInBatches(batchSize: 20);
```

## Database Rules

This package also supports database rules like `unique`, `exists`.

```php
$validator = Validator::make($data, [
    '*.name' => 'required',
    '*.email' => 'email:strict|unique:contacts,email',
]);
```

## Form Request

You can use the `validateInBatches` method in a form request.

```php
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class StoreContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            '*.name' => 'required',
            '*.email' => 'email:strict|unique:contacts,email',
        ];
    }

    public function withValidator(Validator $validator)
    {
        if (method_exists($validator, 'validateInBatches')) {
            $validator->validateInBatches(batchSize: 100);
        }
    }
}
```

## Before and After Optimization

### Before using the package (N+1 problem)

![Before Optimization](https://github.com/user-attachments/assets/e1c8c3a6-d7eb-423b-8448-8d5cc6e2d968)

### After using the package (Optimized)

![After Optimization](https://github.com/user-attachments/assets/4c2353a1-4571-440c-90a4-d7221ec64a44)

## Contributions

Contributions are welcome. If you find any bugs or have ideas for improvements, please open an issue or send a pull request. Make sure to follow the contribution guidelines.

## Author

Laravel Batch Validation was created by [DAZZA](https://github.com/dazza-dev).

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).
