<?php

class Validator
{
    public array $errors = [];
    private array $data;

    public function __construct(array $input)
    {
        $this->data = [];
        foreach ($input as $k => $v) {
            $this->data[$k] = is_string($v) ? trim($v) : $v;
        }
    }

    public function get(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_string($v) ? $v : '';
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function add(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    /** Text with minimum / maximum length. */
    public function text(string $field, string $label, int $min = 1, int $max = 255): bool
    {
        $v = $this->get($field);
        if ($v === '') {
            if ($min > 0) {
                $this->add($field, "$label is required.");
                return false;
            }
            return true;
        }
        $len = mb_strlen($v);
        if ($len < $min) {
            $this->add($field, "$label must be at least $min characters.");
            return false;
        }
        if ($len > $max) {
            $this->add($field, "$label must not be longer than $max characters.");
            return false;
        }
        return true;
    }

    /** Text that must match a regular expression. */
    public function pattern(string $field, string $label, string $regex, string $message): bool
    {
        $v = $this->get($field);
        if ($v === '') {
            $this->add($field, "$label is required.");
            return false;
        }
        if (!preg_match($regex, $v)) {
            $this->add($field, $message);
            return false;
        }
        return true;
    }

    /**
     * Decimal number (max 2 decimal places).
     * $exclusiveMin = true means the value must be GREATER than $min (e.g. > 0).
     */
    public function decimal(string $field, string $label, float $min, float $max, bool $exclusiveMin = false): bool
    {
        $v = $this->get($field);
        if ($v === '') {
            $this->add($field, "$label is required.");
            return false;
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            $this->add($field, "$label must be a valid positive number (up to 2 decimal places).");
            return false;
        }
        $n = (float)$v;
        if (($exclusiveMin && $n <= $min) || (!$exclusiveMin && $n < $min)) {
            $this->add($field, $exclusiveMin ? "$label must be greater than " . $min . '.' : "$label must be at least $min.");
            return false;
        }
        if ($n > $max) {
            $this->add($field, "$label must not be more than " . number_format($max, 2, '.', ',') . '.');
            return false;
        }
        return true;
    }

    /** Whole number between $min and $max. */
    public function integer(string $field, string $label, int $min, int $max): bool
    {
        $v = $this->get($field);
        if ($v === '') {
            $this->add($field, "$label is required.");
            return false;
        }
        if (!preg_match('/^\d+$/', $v)) {
            $this->add($field, "$label must be a whole number.");
            return false;
        }
        $n = (int)$v;
        if ($n < $min || $n > $max) {
            $this->add($field, "$label must be between $min and $max.");
            return false;
        }
        return true;
    }

    /** A real calendar date in YYYY-MM-DD format. Optional limit: not after $notAfter. */
    public function date(string $field, string $label, ?string $notAfter = null): bool
    {
        $v = $this->get($field);
        if ($v === '') {
            $this->add($field, "$label is required.");
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v || (int)$d->format('Y') < 1990 || (int)$d->format('Y') > 2100) {
            $this->add($field, "$label must be a valid date.");
            return false;
        }
        if ($notAfter !== null && $v > $notAfter) {
            $this->add($field, "$label cannot be in the future.");
            return false;
        }
        return true;
    }

    /** $laterField must not be earlier than $earlierField (both must already be valid dates). */
    public function dateNotBefore(string $earlierField, string $laterField, string $message): bool
    {
        if (isset($this->errors[$earlierField]) || isset($this->errors[$laterField])) {
            return true; // already reported
        }
        if ($this->get($laterField) < $this->get($earlierField)) {
            $this->add($laterField, $message);
            return false;
        }
        return true;
    }

    /**
     * The chosen value must be an existing record (foreign key check).
     * $table and $column are written by us in code, never typed by the user.
     */
    public function exists(string $field, string $label, string $table, string $column): bool
    {
        $v = $this->get($field);
        if ($v === '' || !preg_match('/^\d+$/', $v)) {
            $this->add($field, "Please select a valid $label.");
            return false;
        }
        $st = db()->prepare("SELECT 1 FROM $table WHERE $column = ?");
        $st->execute([(int)$v]);
        if (!$st->fetchColumn()) {
            $this->add($field, "The selected $label does not exist.");
            return false;
        }
        return true;
    }

    /** The value must be one of the allowed values. */
    public function inList(string $field, string $label, array $allowed): bool
    {
        if (!in_array($this->get($field), array_map('strval', $allowed), true)) {
            $this->add($field, "Please select a valid $label.");
            return false;
        }
        return true;
    }
}
