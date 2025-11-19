<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class EnvEditor
{
    public static function set(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $line = $key.'='.$value;

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", $line, $content);
        } else {
            $content .= PHP_EOL.$line.PHP_EOL;
        }

        File::put($path, $content);
    }
}
