<?php
declare(strict_types=1);

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

class Config
{
    private static array $env = [];
    private static bool $loaded = false;

    public static function init(): void
    {
        if (self::$loaded) {
            return;
        }

        $rootPath = dirname(__DIR__);
        $envFile = $rootPath . '/.env';

        if (class_exists('Dotenv\\Dotenv') && file_exists($envFile)) {
            $dotenv = Dotenv\Dotenv::createImmutable($rootPath);
            $dotenv->safeLoad();
            self::$env = $_ENV;
        } elseif (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                self::$env[$key] = $val;
                $_ENV[$key] = $val;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::init();
        }

        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

Config::init();
