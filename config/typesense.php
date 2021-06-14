<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Typesense schemas
    |--------------------------------------------------------------------------
    |
    | Typesense indexes are static and fields need to be defined when created,
    | this includes options such as the field type or facet options.
    |
    | The fields will default to String values, so you only need to define
    | facets or any other configuration values that you require.
    |
    */

    'schema' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Defaults
    |--------------------------------------------------------------------------
    |
    | Here you can specify default configuration to be applied to all schemas.
    |
    */

    'defaults' => [
        'fields' => [
            [
                'name'  => '.*',
                'type'  => 'auto'
            ],
        ]
    ],
];