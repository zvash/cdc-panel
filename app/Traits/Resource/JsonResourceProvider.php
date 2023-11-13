<?php

namespace App\Traits\Resource;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait JsonResourceProvider
{
    /**
     * Show the field if it isn't hidden.
     *
     * @param  string  $field
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function whenNotHidden(string $field)
    {
        return $this->when($this->{$field} && ! in_array($field, $this->getHidden()), $this->{$field});
    }

    /**
     * Set location header for the resource response.
     *
     * @param  string  $routeName
     * @param  array  $parameter
     * @return \Illuminate\Http\JsonResponse
     */
    public function withLocation(string $routeName, array $parameter = [])
    {
        return $this->response()
            ->header('Location', route(...func_get_args()))
            ->setStatusCode(201);
    }

    /**
     * Add additional meta data to the resource meta response.
     *
     * @param  array  $data
     * @return $this
     */
    protected function mergeAdditional(array $data)
    {
        $meta = $this->additional['meta'] ?? [];

        $data = array_map(function ($value) {
            if (is_null($value)) {
                return new MissingValue;
            }

            if (is_array($value) && empty($value)) {
                return new MissingValue;
            }

            return $value;
        }, array_merge($data, $meta));

        return $this->additional($this->filter(['meta' => $data]));
    }

    /**
     * Check relationship loaded and get its value.
     *
     * @param  string  $relation
     * @param  string|null  $foreignKey
     * @param  string|null  $resource
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function whenLoadedAndNotEmpty(string $relation, ?string $resource = null, ?string $foreignKey = null)
    {
        $foreignKey = $foreignKey ?? Str::snake($relation).'_id';

        return $this->when(
            $this->{$foreignKey},
            function () use ($relation, $resource) {
                $resource = $resource ?? $this->resourceClass($relation);

                return new $resource(
                    $this->whenLoaded($relation) ?? new MissingValue
                );
            }
        );
    }

    /**
     * Check relationship loaded and get the first resource from it.
     *
     * @param  string  $relation
     * @param  string|null  $resource
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function whenLoadedGetFirst(string $relation, ?string $resource = null)
    {
        return $this->whenLoaded($relation, function () use ($relation, $resource) {
            $resource = $resource ?? $this->resourceClass($relation);

            return new $resource(
                $this->when(
                    $this->{$relation}->isNotEmpty(),
                    $this->{$relation}->first()
                )
            );
        });
    }

    /**
     * Check relationship loaded and get all resources from it.
     *
     * @param  string  $relation
     * @param  string|null  $resource
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function whenLoadedGetAll(string $relation, ?string $resource = null)
    {
        return $this->whenLoaded($relation, function () use ($relation, $resource) {
            $resource = $resource ?? $this->resourceClass($relation);

            return $resource::collection(
                $this->when($this->{$relation}->isNotEmpty(), $this->{$relation})
            );
        });
    }

    /**
     * If specific field has file and it's not empty.
     *
     * @param  string  $field
     * @param  string  $disk
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function whenHasFile(string $field, string $disk = 's3')
    {
        return $this->when(
            Storage::disk($disk)->exists($this->{$field}),
            function () use ($disk, $field) {
                return Storage::disk($disk)->url($this->{$field});
            }
        );
    }

    /**
     * Get timestamps from dateTime fields.
     *
     * @param  string  $field
     * @param  Closure|null  $callable
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function whenHasDate(string $field, ?callable $callable = null)
    {
        return $this->when(
            $this->{$field} && $this->{$field} instanceof CarbonInterface,
            function () use ($field, $callable) {
                return $callable
                    ? $callable($this->{$field})
                    : $this->{$field}->timestamp;
            }
        );
    }

    /**
     * Get morph resource if it is loaded and has a value.
     *
     * @param  string  $morph
     * @return \Illuminate\Http\Resources\MissingValue|\Illuminate\Http\Resources\Json\JsonResource
     */
    protected function getMorphResource(string $morph)
    {
        $class = $this->getMorphType($morph);

        if ($class instanceof MissingValue) {
            return $class;
        }

        $resource = $this->resourceClass($class);

        if (! class_exists($resource)) {
            return new MissingValue;
        }

        return new $resource($this->{$morph});
    }

    /**
     * Get morph type.
     *
     * @param  string  $morph
     * @return \Illuminate\Http\Resources\MissingValue|string
     */
    protected function getMorphType(string $morph)
    {
        if (! $this->morphIsLoaded($morph)) {
            return new MissingValue;
        }

        return class_basename($this->{$morph.'_type'});
    }

    /**
     * Check morph relation exist & loaded or not?
     *
     * @param  string  $morph
     * @return bool
     */
    protected function morphIsLoaded(string $morph)
    {
        return $this->relationLoaded($morph) && ! empty($this->{$morph});
    }

    /**
     * Namespace of all resources.
     *
     * @return string
     */
    private function resourceClass(string $relation)
    {
        return '\\App\\Http\\Resources\\'.ucwords(Str::singular($relation));
    }
}
