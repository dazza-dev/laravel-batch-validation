# Laravel Batch Validation

When performing array validations in Laravel with unique rules, Laravel validates one record at a time, causing an N+1 query problem. This is a common issue when importing data from an Excel file and validating it before inserting it into the database. This package solves the problem by batching the unique validations and using whereIn to prevent the N+1 problem.

## Setup

- Install

```bash
composer require dazza-dev/laravel-batch-validation
```

## Contributions

Contributions are welcome. If you find any bugs or have ideas for improvements, please open an issue or send a pull request. Make sure to follow the contribution guidelines.

## Author

Laravel Batch Validation was created by [DAZZA](https://github.com/dazza-dev).

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).
