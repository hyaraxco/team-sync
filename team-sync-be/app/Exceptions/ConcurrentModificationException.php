<?php

namespace App\Exceptions;

use RuntimeException;

class ConcurrentModificationException extends RuntimeException
{
    public function __construct(string $message = 'Record was modified by another user. Please refresh and try again.')
    {
        parent::__construct($message);
    }
}
