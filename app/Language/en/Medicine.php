<?php

return [
    'title' => 'Medicine Master',

    'table' => [
        'code'      => 'Code',
        'name'      => 'Medicine Name',
        'unit'      => 'Unit',
        'status'    => 'Status',
        'actions'   => 'Actions',
        'active'    => 'active',
        'inactive'  => 'inactive',
        'empty'     => 'No medicine matches.',
        'add'       => 'Add Medicine',
        'edit'      => 'Edit',
    ],

    'form' => [
        'create_title' => 'Add Medicine',
        'edit_title'   => 'Edit Medicine',
        'code'         => 'Code',
        'name'         => 'Medicine Name',
        'unit'         => 'Unit',
        'is_active'    => 'Status',
        'active_hint'  => 'Inactive medicines are hidden from the reception form but stay listed here.',
        'save'         => 'Save',
        'cancel'       => 'Cancel',
    ],

    'filter' => [
        'search'           => 'Search',
        'search_placeholder' => 'Code or medicine name',
        'status'           => 'Status',
        'status_all'       => 'All statuses',
        'status_active'    => 'Active only',
        'status_inactive'  => 'Inactive only',
        'show'             => 'Show',
    ],

    'api' => [
        'not_found'      => 'Medicine not found.',
        'created'        => 'Medicine created.',
        'updated'        => 'Medicine updated.',
        'failed'         => 'Failed.',
        'store_failed'   => 'Failed to save medicine: {0}',
        'update_failed'  => 'Failed to update medicine: {0}',
        'forbidden'      => 'You are not allowed to change the medicine master.',
        'invalid_status' => 'status must be one of all, active, inactive.',
    ],

    'validation' => [
        'code_required'      => 'code is required.',
        'code_too_long'      => 'code must be at most 50 characters.',
        'code_taken'         => 'code {0} is already used by another medicine.',
        'name_required'      => 'name is required.',
        'name_too_long'      => 'name must be at most 200 characters.',
        'unit_required'      => 'unit is required.',
        'unit_too_long'      => 'unit must be at most 50 characters.',
        'is_active_invalid'  => 'is_active must be true or false.',
    ],

    'js' => [
        'load_failed'    => 'Failed to load medicines.',
        'contact_failed' => 'Cannot reach the server.',
    ],
];
