<?php

namespace App\Data;

use App\Support\FilePath;

final readonly class ThemeDefinition
{
    public function __construct(
        public string $id,
        public string $path,
    ) {}

    public function namespace(): string
    {
        return "theme-{$this->id}";
    }

    public function viewPath(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . 'views';
    }

    public function componentPath(): string
    {
        return $this->viewPath() . DIRECTORY_SEPARATOR . 'components';
    }

    public function sourceDirectory(): string
    {
        return FilePath::relativeTo($this->path, realpath(base_path()) ?: base_path())
            ?? str_replace('\\', '/', $this->path);
    }

    /** @return list<string> */
    public function entrypoints(): array
    {
        return ['css/app.css', 'js/app.js'];
    }

    public function viewFile(string $key): string
    {
        return $this->sourceDirectory() . '/views/' . str_replace('.', '/', $key) . '.blade.php';
    }
}
