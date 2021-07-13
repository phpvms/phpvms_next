<?php

namespace App\Exceptions;

class FlightNotFound extends AbstractHttpException
{
    private $pirep;

    public function __construct($pirep)
    {
        $this->pirep = $pirep;
        parent::__construct(404, 'Flight not found');
    }

    // Return the RFC 7807 error type (without the URL root)
    public function getErrorType(): string
    {
        return 'flight-not-found';
    }

    // Get the detailed error string
    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    // Return an array with the error details, merged with the RFC7807 response
    public function getErrorMetadata(): array
    {
        return [];
    }
}
