<?php

namespace App\Services;

use App\Policies\ReceptionPolicy;
use App\Repositories\ReceptionRepository;
use App\Validation\ReceptionValidator;

class ReceptionService
{
    public function __construct(
        private readonly ReceptionRepository $receptions = new ReceptionRepository(),
        private readonly ReceptionValidator $validator = new ReceptionValidator(),
        private readonly ReceptionPolicy $policy = new ReceptionPolicy(),
    ) {
    }
}
