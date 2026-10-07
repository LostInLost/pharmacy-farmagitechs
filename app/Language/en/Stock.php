<?php

return [
    'title' => 'Stock Report',

    'filter' => [
        'on_date'  => 'Expiry check date',
        'status'   => 'Batch status',
        'status_all'       => 'All statuses',
        'status_available' => 'Available only',
        'status_expired'   => 'Expired only',
        'show'     => 'Show',
        'hint'     => 'Quantities are always calculated from all stored transactions; the date only determines expiry status.',
    ],

    'table' => [
        'code'      => 'Code',
        'medicine'  => 'Medicine',
        'unit'      => 'Unit',
        'physical'  => 'Physical',
        'available' => 'Available',
        'expired'   => 'Expired',
        'batches'   => 'Batches',
        'batch_no'  => 'Batch',
        'expires_on' => 'Expires on',
        'quantity'  => 'Quantity',
        'status'    => 'Status',
        'no_batch'  => 'no batches yet',
        'batch_count' => '{0} batches',
        'status_available' => 'available',
        'status_expired'   => 'expired',
    ],

    'api' => [
        'invalid_date' => 'on_date must be in YYYY-MM-DD format.',
    ],

    'js' => [
        'load_failed'      => 'Failed to load the stock report.',
        'contact_failed'   => 'Cannot reach the server.',
        'no_batch'         => 'no batches yet',
        'batch_count'      => '{0} batches',
        'status_available' => 'available',
        'status_expired'   => 'expired',
        'filter_available' => 'No available batches on this date.',
        'filter_expired'   => 'No expired batches on this date.',
        'filter_empty'     => 'No medicines match this status.',
    ],
];
