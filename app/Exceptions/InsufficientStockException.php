<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Insufficient stock available for this operation.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
