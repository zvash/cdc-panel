<?php

namespace App\Traits\Seeder;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage as FileSystem;

trait Storage
{
    /**
     * Store a file from a specific path to a destination on a disk.
     *
     * @param  string  $to
     * @param  string|null  $path
     * @param  string  $disk
     * @return string|null
     */
    protected function store(string $to, ?string $path, string $disk = 'public')
    {
        return $path ? FileSystem::disk($disk)->putFile($to, new File($this->getSeederPath($path))) : null;
    }

    /**
     * get seeder resource path.
     *
     * @param  string  $path
     * @return string
     */
    protected function getSeederPath(string $path)
    {
        return resource_path('seeders/'.$path);
    }
}
