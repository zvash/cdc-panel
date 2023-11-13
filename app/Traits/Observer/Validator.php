<?php

namespace App\Traits\Observer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait Validator
{
    /**
     * Validation object.
     *
     * @var \Illuminate\Contracts\Validation\Validator|\Illuminate\Contracts\Validation\Factory
     */
    protected $validator;

    /**
     * The observer resource model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $resource;

    /**
     * Validate resource model with validator.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $resource
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    protected function validate(Model $resource)
    {
        $this->resource = $resource;

        throw_if(
            $this->validator()->fails(), ValidationException::class, $this->validator
        );
    }

    /**
     * Make the validator.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator()
    {
        return $this->validator = validator()->make(
            $this->data(), $this->rules(), $this->messages()
        );
    }

    /**
     * The data array for validator.
     *
     * @return array
     */
    abstract protected function data();

    /**
     * The rules array for validator.
     *
     * @return array
     */
    abstract protected function rules();

    /**
     * The messages array for validator.
     *
     * @return array
     */
    protected function messages()
    {
        return [];
    }
}
