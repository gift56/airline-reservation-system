<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Loads allowlisted values from the project-local environment file.
 */
final class Environment
{
    /**
     * @param list<string> $allowedKeys
     */
    public static function load(string $projectRoot, array $allowedKeys): void
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            return;
        }

        if (!is_readable($path)) {
            throw new RuntimeException('The project .env file exists but cannot be read.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException('The project .env file could not be read.');
        }

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || $trimmedLine[0] === '#') {
                continue;
            }

            if (preg_match('/\A([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\z/', $trimmedLine, $matches) !== 1) {
                throw new RuntimeException(sprintf('Invalid .env entry on line %d.', $index + 1));
            }

            $key = $matches[1];

            if (!in_array($key, $allowedKeys, true) || getenv($key) !== false) {
                continue;
            }

            $value = self::parseValue($matches[2], $index + 1);

            if (!putenv($key . '=' . $value)) {
                throw new RuntimeException(sprintf('Unable to load %s from the project .env file.', $key));
            }
        }
    }

    private static function parseValue(string $rawValue, int $lineNumber): string
    {
        $value = trim($rawValue);

        if ($value === '') {
            return '';
        }

        $quote = $value[0];

        if ($quote === '"' || $quote === "'") {
            $quotedValuePattern = '/\A' . preg_quote($quote, '/') . '(.*)' . preg_quote($quote, '/')
                . '(?:\s+#.*)?\z/D';

            if (preg_match($quotedValuePattern, $value, $matches) !== 1) {
                throw new RuntimeException(sprintf('Unclosed quoted value in .env on line %d.', $lineNumber));
            }

            return $matches[1];
        }

        $commentPosition = preg_match('/\s+#/', $value, $comment, PREG_OFFSET_CAPTURE);

        if ($commentPosition === 1) {
            $value = substr($value, 0, (int) $comment[0][1]);
        }

        return trim($value);
    }
}
