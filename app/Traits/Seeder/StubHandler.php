<?php

namespace App\Traits\Seeder;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;

trait StubHandler
{
    /**
     * Read a file from stubs folder.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read(string $path)
    {
        $file = database_path('stubs'.DIRECTORY_SEPARATOR.$path);

        throw_unless(file_exists($file), FileNotFoundException::class);

        return file_get_contents($file);
    }

    /**
     * Read a file from stubs folder as decoded json.
     *
     * @param  string  $path
     * @param  bool  $assoc
     * @return \Illuminate\Support\Collection
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function readAsJson(string $path, bool $assoc = true)
    {
        return collect(json_decode($this->read($path), $assoc) ?: []);
    }

    /**
     * Read a seed file from stubs folder base on seeder class.
     *
     * @param  bool  $assoc
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function seed(bool $assoc = true)
    {
        return $this->readAsJson('seeds'.DIRECTORY_SEPARATOR.$this->getSeedFile(), $assoc);
    }

    /**
     * Get seed filename for specific seeder class.
     *
     * @param  string|null  $class
     * @return string
     */
    protected function getSeedFile(?string $class = null)
    {
        $file = class_basename($class ?? get_class($this));

        return Str::finish(Str::kebab(Str::beforeLast($file, 'Seeder')), '.json');
    }

    /**
     * Call seed method if the method doesn't exist.
     *
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function __call(string $name, array $arguments)
    {
        return $this->seed(...$arguments);
    }
}
