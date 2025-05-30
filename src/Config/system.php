<?php

return [
    [
        'key'    => 'sales.payment_methods.bkash_payment',
        'name'   => 'bKash Payment',
        'sort'   => 5,
        'fields' => [
            [
                'name'          => 'title',
                'title'         => 'Title',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => true,
            ],
            [
                'name'          => 'description',
                'title'         => 'Description',
                'type'          => 'textarea',
                'channel_based' => false,
                'locale_based'  => true,
            ],
            [
                'name'          => 'sandbox',
                'title'         => 'Sandbox',
                'type'          => 'boolean',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'username',
                'title'         => 'bKash Username',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'password',
                'title'         => 'bKash Password',
                'type'          => 'password',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'app_key',
                'title'         => 'bKash App Key',
                'type'          => 'password',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'app_secret',
                'title'         => 'bKash App Secret',
                'type'          => 'password',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'sandbox_base_url',
                'title'         => 'Sandbox Base URL',
                'type'          => 'text',
                'info'          => 'Default: https://tokenized.sandbox.bka.sh',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'live_base_url',
                'title'         => 'Live Base URL',
                'type'          => 'text',
                'info'          => 'Default: https://tokenized.pay.bka.sh',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'success_url',
                'title'         => 'Custom Success URL (Optional)',
                'type'          => 'text',
                'validation'    => '',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'fail_url',
                'title'         => 'Custom Failure URL (Optional)',
                'type'          => 'text',
                'validation'    => '',
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
                'name'          => 'active',
                'title'         => 'admin::app.admin.system.status',
                'type'          => 'boolean',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
        ],
    ],
];
