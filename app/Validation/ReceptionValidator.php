<?php

namespace App\Validation;

use CodeIgniter\HTTP\IncomingRequest;

class ReceptionValidator
{
    /**
     * @return array<string, string> field => message
     */
    public function validate(IncomingRequest $request, ?int $receptionId = null): array
    {
        return [];
    }
}
