<?php

namespace FreshCart\Operations\Exceptions;

use RuntimeException;

/**
 * A business-rule violation whose message is safe to show to the user.
 */
class OperationsException extends RuntimeException
{
    public static function trans(string $key, array $replace = []): self
    {
        return new self(trans('operations::app.errors.'.$key, $replace));
    }
}
