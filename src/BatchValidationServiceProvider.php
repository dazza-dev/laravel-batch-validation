<?php

namespace DazzaDev\BatchValidation;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory;

class BatchValidationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->app->extend('validator', function (Factory $factory) {
            $factory->resolver(function ($translator, $data, $rules, $messages, $attributes) {
                return new BatchValidator($translator, $data, $rules, $messages, $attributes);
            });

            return $factory;
        });
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        //
    }
}
