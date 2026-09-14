<?php

declare(strict_types=1);

namespace App\Support;

final class FormErrors
{
    /** @param array<string,string> $errors */
    public static function remember(string $scope, array $errors): void
    {
        $_SESSION['field_errors'][$scope] = $errors;
    }

    /** @return array<string,string> */
    public static function pull(string $scope): array
    {
        $errors = $_SESSION['field_errors'][$scope] ?? [];
        unset($_SESSION['field_errors'][$scope]);
        return is_array($errors) ? $errors : [];
    }

    /** @param array<string,string> $errors */
    public static function attributes(array $errors, string $field, string $id): string
    {
        if (!isset($errors[$field])) {
            return '';
        }
        return ' aria-invalid="true" aria-describedby="' . htmlspecialchars($id . '-error', ENT_QUOTES)
            . '" data-error="' . htmlspecialchars($errors[$field], ENT_QUOTES) . '"';
    }

    /** @param array<string,string> $errors */
    public static function hint(array $errors, string $field, string $id, string $prefix): string
    {
        return isset($errors[$field])
            ? '<p class="' . $prefix . '-field__error" data-field-error id="' . htmlspecialchars($id . '-error', ENT_QUOTES) . '">' . htmlspecialchars($errors[$field]) . '</p>'
            : '';
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,array{0:string,1:string}> $fields
     */
    public static function summary(array $errors, array $fields, string $prefix): string
    {
        if ($errors === []) {
            return '';
        }
        $html = '<section class="' . $prefix . '-error-summary" data-' . $prefix . '-error-summary data-server-errors tabindex="-1" aria-label="Angaben prüfen"><h2>'
            . count($errors) . (count($errors) === 1 ? ' Angabe prüfen' : ' Angaben prüfen') . '</h2><ul>';
        foreach ($errors as $name => $message) {
            $html .= '<li>';
            if (isset($fields[$name])) {
                [$id, $label] = $fields[$name];
                $html .= '<a href="#' . htmlspecialchars($id, ENT_QUOTES) . '">' . htmlspecialchars($label . ': ' . $message) . '</a>';
            } else {
                $html .= htmlspecialchars($message);
            }
            $html .= '</li>';
        }
        return $html . '</ul></section>';
    }
}
