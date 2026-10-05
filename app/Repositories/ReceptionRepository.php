<?php

namespace App\Repositories;

use App\Models\ReceptionItemModel;
use App\Models\ReceptionLogModel;
use App\Models\ReceptionModel;

class ReceptionRepository
{
    public function __construct(
        private readonly ReceptionModel $receptions = new ReceptionModel(),
        private readonly ReceptionItemModel $items = new ReceptionItemModel(),
        private readonly ReceptionLogModel $logs = new ReceptionLogModel(),
    ) {
    }
}
