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
    ],
    'ApiUsers' => [
        /* available user roles */
        'roles' => [
            'user' => __d('EvilCorp/AwsCognito', 'API User'),
        ],
        /* the max amount of errors alloweds before the validation process of the imported data is halted */
        'import_max_errors' => 10,

        /* the limit of accepted rows in the importing CSV data */
        'import_max_rows' => 500,

        /* use EvilCorp/AwsS3Upload to store the avatars */
        'use_aws_s3' => true,
    ]
];

return $config;