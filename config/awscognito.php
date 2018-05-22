<?php
use Cake\Core\Configure;

$config = [
	'AwsCognito' => [
        'AccessKeys' => [
            'id' => 'NSWPXE30F49XAOF',
            'secret' => 'QIQNxRO2425bb040e4adc8cc02fae05063c3c'
        ],
        'UserPool' => [
            'id' => 'us-east-2_rjaob1HaR',
        ],
        'IdentityProviderClient' => [
            'settings' => [], //https://docs.aws.amazon.com/sdkforruby/api/Aws/CognitoIdentityProvider/Client.html#initialize-instance_method
        ],
        'ApiUsers' => [
            'roles' => [
                'agent'     => 'Agente',
                'dashboard' => 'Panel',
            ],
        ]
    ]
];

return $config;