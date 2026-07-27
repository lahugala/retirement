<?php
class Validator {
    private array $errors = [];

    public function required($value, string $field): self {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[] = "$field is required";
        }
        return $this;
    }

    public function email($value, string $field): self {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "$field must be a valid email address";
        }
        return $this;
    }

    public function numeric($value, string $field): self {
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[] = "$field must be a number";
        }
        return $this;
    }

    public function min($value, float $min, string $field): self {
        if (is_numeric($value) && (float)$value < $min) {
            $this->errors[] = "$field must be at least $min";
        }
        return $this;
    }

    public function max($value, float $max, string $field): self {
        if (is_numeric($value) && (float)$value > $max) {
            $this->errors[] = "$field must not exceed $max";
        }
        return $this;
    }

    public function inArray($value, array $allowed, string $field): self {
        if (!empty($value) && !in_array($value, $allowed, true)) {
            $this->errors[] = "$field must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function uuid($value, string $field): self {
        if (!empty($value) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $this->errors[] = "$field must be a valid UUID";
        }
        return $this;
    }

    public function date($value, string $field): self {
        if (!empty($value) && !strtotime($value)) {
            $this->errors[] = "$field must be a valid date";
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function clear(): void {
        $this->errors = [];
    }
}
