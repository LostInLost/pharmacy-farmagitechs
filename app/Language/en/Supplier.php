<?php

return [
    'title' => 'Supplier Master',

    'table' => [
        'name'      => 'Supplier Name',
        'status'    => 'Status',
        'actions'   => 'Actions',
        'active'    => 'active',
        'inactive'  => 'inactive',
        'empty'     => 'No supplier matches.',
        'add'       => 'Add Supplier',
        'edit'      => 'Edit',
    ],

    'form' => [
        'create_title' => 'Add Supplier',
        'edit_title'   => 'Edit Supplier',
        'name'         => 'Supplier Name',
        'is_active'    => 'Status',
        'active_hint'  => 'Inactive suppliers are hidden from the reception form but stay listed here.',
        'save'         => 'Save',
        'cancel'       => 'Cancel',
    ],

    'filter' => [
        'search'             => 'Search',
        'search_placeholder' => 'Supplier name',
        'status'             => 'Status',
        'status_all'         => 'All statuses',
        'status_active'      => 'Active only',
        'status_inactive'    => 'Inactive only',
        'show'               => 'Show',
    ],

    'api' => [
        'not_found'      => 'Supplier not found.',
        'created'        => 'Supplier created.',
        'updated'        => 'Supplier updated.',
        'failed'         => 'Failed.',
        'store_failed'   => 'Failed to save supplier: {0}',
        'update_failed'  => 'Failed to update supplier: {0}',
        'forbidden'      => 'You are not allowed to change the supplier master.',
        'invalid_status' => 'status must be one of all, active, inactive.',
    ],

    'validation' => [
        'name_required'     => 'name is required.',
        'name_too_long'     => 'name may not exceed 150 characters.',
        'name_taken'        => 'name {0} is already used by another supplier.',
        'is_active_invalid' => 'is_active must be true or false.',
    ],
];
