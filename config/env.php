<?php

function load_environment(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . ".env";

    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException("Unable to read the environment file.");
    }

    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);

        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }

        if (str_starts_with($line, "export ")) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, "=");
        if ($separator === false) {
            throw new RuntimeException("Invalid environment variable on line " . ($lineNumber + 1) . ".");
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/", $name)) {
            throw new RuntimeException("Invalid environment variable name on line " . ($lineNumber + 1) . ".");
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        } else {
            $commentPosition = strpos($value, " #");
            if ($commentPosition !== false) {
                $value = rtrim(substr($value, 0, $commentPosition));
            }
        }

        if (getenv($name) === false) {
            putenv($name . "=" . $value);
            $_ENV[$name] = $value;
        }
    }
}

function get_env(string $name, ?string $default = null): ?string
{
    load_environment();

    $value = getenv($name);
    return $value === false ? $default : $value;
}

function environment_value(string $name, ?string $default = null): ?string
{
    return get_env($name, $default);
}
