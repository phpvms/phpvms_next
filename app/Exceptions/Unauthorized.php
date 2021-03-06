<?php

namespace App\Exceptions;

use Exception;

class Unauthorized extends AbstractHttpException
{
    private $exception;

    public function __construct(Exception $exception)
    {
        $this->exception = $exception;
        parent::__construct(
            403,
            $exception->getMessage()
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'unauthorized';
    }

    /**
     * Get the detailed error string
     */
    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorMetadata(): array
    {
        return [];
    }
}
