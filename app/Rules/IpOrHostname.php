<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class IpOrHostname implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        $pattern = '/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*$/';

        return (bool) preg_match($pattern, $value);
    }

    public function message(): string
    {
        return 'El campo :attribute debe ser una IPv4 válida o un nombre de dominio.';
    }
}
