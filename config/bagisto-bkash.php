<?php

return [
    /*
    |--------------------------------------------------------------------------
    | bKash Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for bKash payment integration with Bagisto
    |
    */

    'payment_methods' => [
        'bkash' => [
            'code'        => 'bkash',
            'title'       => 'BKash',
            'description' => 'BKash',
            'class'       => 'Ihasan\Bkash\Payment\Bkash',
            'active'      => true,
            'sort'        => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | bKash System Configuration
    |--------------------------------------------------------------------------
    |
    | System configuration for bKash integration
    |
    */
    
    'system_config' => [
        [
            'key'    => 'sales.payment_methods.bkash',
            'name'   => 'bKash Payment',
            'info'   => 'Configure bKash payment method settings',
            'sort'   => 1,
            'fields' => [
                [
                    'name'          => 'title',
                    'title'         => 'bkash Payment Method Title',
                    'type'          => 'text',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => true,
                ],
                [
                    'name'          => 'description',
                    'title'         => 'bkash Payment Method Description',
                    'type'          => 'textarea',
                    'channel_based' => false,
                    'locale_based'  => true,
                ],
                [
                    'name'          => 'bkash_sandbox',
                    'title'         => 'Sandbox Mode',
                    'type'          => 'boolean',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
                [
                    'name'          => 'sandbox_base_url',
                    'title'         => 'Sandbox Base URL',
                    'type'          => 'text',
                    'validation'    => 'required_if:bkash_sandbox,1',
                    'channel_based' => false,
                    'locale_based'  => false,
                    'value'         => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
                ],
                [
                    'name'          => 'live_base_url',
                    'title'         => 'Live Base URL',
                    'type'          => 'text',
                    'validation'    => 'required_if:bkash_sandbox,0',
                    'channel_based' => false,
                    'locale_based'  => false,
                    'value'         => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
                ],
                [
                    'name'          => 'bkash_username',
                    'title'         => 'bkash Username',
                    'type'          => 'text',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
                [
                    'name'          => 'image',
                    'title'         => 'bKash Logo',
                    'type'          => 'file',
                    'validation'    => 'mimes:bmp,jpeg,jpg,png,webp',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
                [
                    'name'          => 'bkash_password',
                    'title'         => 'bkash Password',
                    'type'          => 'password',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
                [
                    'name'          => 'bkash_app_key',
                    'title'         => 'bkash App Key',
                    'type'          => 'password',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
                [
                    'name'          => 'bkash_app_secret',
                    'title'         => 'bkash App Secret',
                    'type'          => 'password',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
                [
                    'name'          => 'active',
                    'title'         => 'Status',
                    'type'          => 'boolean',
                    'validation'    => 'required',
                    'channel_based' => false,
                    'locale_based'  => false,
                ],
            ],
        ],
    ],
];
