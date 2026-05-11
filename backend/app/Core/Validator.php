<?php
namespace App\Core;

use App\Exceptions\ValidationException;

class Validator {
    private const DEFAULT_ENUMS = [
        'visibility' => ['private', 'authenticated', 'unlisted', 'public'],
        'rights_status' => ['owned', 'licensed', 'expired'],
        'status' => ['draft', 'published', 'archived'],
        'role' => ['user', 'admin'],
    ];

    public function validate(array $data, array $rules): array {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $parsedRules = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);

            foreach ($parsedRules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $isRequired = $name === 'required';

                if (!$isRequired && $this->isEmpty($value)) {
                    continue;
                }

                $message = $this->applyRule($field, $value, $name, $arg);
                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Validation failed', $errors);
        }

        return $data;
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $arg): ?string {
        return match ($rule) {
            'required' => $this->isEmpty($value) ? 'The field is required.' : null,
            'string' => is_string($value) ? null : 'The field must be a string.',
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'The field must be an integer.',
            'bool' => $this->isBoolLike($value) ? null : 'The field must be a boolean.',
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? null : 'The field must be a valid email address.',
            'enum' => $this->validateEnum($field, $value, $arg),
            'min' => $this->validateMin($value, $arg),
            'max' => $this->validateMax($value, $arg),
            'regex' => $this->validateRegex($value, $arg),
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value) ? null : 'The field must be a valid UUID.',
            default => null,
        };
    }

    private function validateEnum(string $field, mixed $value, ?string $arg): ?string {
        $allowed = $arg !== null && $arg !== ''
            ? array_map('trim', explode(',', $arg))
            : (self::DEFAULT_ENUMS[$field] ?? []);

        if ($allowed === []) {
            return 'The field has no configured enum values.';
        }

        return in_array((string) $value, $allowed, true) ? null : 'The field must be one of: ' . implode(', ', $allowed) . '.';
    }

    private function validateMin(mixed $value, ?string $arg): ?string {
        $min = (int) $arg;

        if (is_numeric($value)) {
            return (float) $value >= $min ? null : "The field must be at least {$min}.";
        }

        return mb_strlen((string) $value) >= $min ? null : "The field must be at least {$min} characters.";
    }

    private function validateMax(mixed $value, ?string $arg): ?string {
        $max = (int) $arg;

        if (is_numeric($value)) {
            return (float) $value <= $max ? null : "The field must be at most {$max}.";
        }

        return mb_strlen((string) $value) <= $max ? null : "The field must be at most {$max} characters.";
    }

    private function validateRegex(mixed $value, ?string $arg): ?string {
        if ($arg === null || $arg === '') {
            return 'The regex rule requires a pattern.';
        }

        return preg_match($arg, (string) $value) ? null : 'The field format is invalid.';
    }

    private function isBoolLike(mixed $value): bool {
        if (is_bool($value)) {
            return true;
        }

        return in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    private function isEmpty(mixed $value): bool {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }
}
