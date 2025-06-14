<?php
namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class TelegramCommandLoader
{
    protected string $controllerNamespace = 'App\Http\Controllers\Telegram';
    protected string $controllerPath;

    public function __construct()
    {
        $this->controllerPath = app_path('Http/Controllers/Telegram');
    }

    public function load(): array
    {
        $commands = [];

        foreach (File::files($this->controllerPath) as $file) {
            $class = $this->controllerNamespace . '\\' . $file->getBasename('.php');

            if (!class_exists($class)) continue;

            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $class) continue;
                if ($method->isConstructor()) continue;

                $command = '/' . Str::snake($method->getName());
                $commands[$command] = $class;
            }
        }

        return $commands;
    }
}
