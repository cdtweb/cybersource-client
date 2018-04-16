<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

try {
    $dotenv = new Dotenv\Dotenv(dirname(__DIR__));
    $dotenv->load();
} catch (\Exception $e) {
}
