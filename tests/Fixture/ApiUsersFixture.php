<?php

namespace EvilCorp\AwsCognito\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ApiUsersFixture
 *
 */
class ApiUsersFixture extends TestFixture
{

    public $fields = [
        'id' => [
            'type'      => 'integer',
            'length'    => 11,
            'null'      => false,
            'default'   => null,
        ],
        'aws_cognito_id' => [
            'type'      => 'uuid',
            'length'    => null,
            'null'      => false,
            'default'   => null,
        ],
        'aws_cognito_username' => [
            'type'      => 'string',
            'null'      => false,
            'default'   => null,
        ],
        'email' => [
            'type'      => 'string',
            'length'    => 255,
            'null'      => false,
            'default'   => null,
        ],
        'active' => [
            'type'      => 'boolean',
            'null'      => false,
            'default'   => true,
        ],
        'role' => [
            'type'      => 'string',
            'length'    => 50,
            'null'      => false,
            'default'   => null,
        ],
        'first_name' => [
            'type'      => 'string',
            'length'    => 50,
            'null'      => true,
            'default'   => null,
        ],
        'last_name' => [
            'type'      => 'string',
            'length'    => 50,
            'null'      => true,
            'default'   => null,
        ],

        'avatar_url' => [
            'type'      => 'string',
            'length'    => 255,
            'null'      => true,
            'default'   => null,
        ],
        'avatar_file_name' => [
            'type'      => 'string',
            'length'    => 255,
            'null'      => true,
            'default'   => null,
        ],
        'avatar_file_dir' => [
            'type'      => 'string',
            'length'    => 255,
            'null'      => true,
            'default'   => null,
        ],
        'avatar_file_size' => [
            'type'      => 'integer',
            'length'    => 11,
            'null'      => true,
            'default'   => null,
        ],
        'avatar_file_type' => [
            'type'      => 'string',
            'length'    => 255,
            'null'      => true,
            'default'   => null,
        ],


        'created_at' => [
            'type'      => 'datetime',
            'null'      => false,
        ],
        'created_by' => [
            'type'      => 'uuid',
            'length'    => null,
            'null'      => true,
            'default'   => null,
        ],
        'modified_at' => [
            'type'      => 'datetime',
            'null'      => true,
        ],
        'modified_by' => [
            'type'      => 'uuid',
            'length'    => null,
            'null'      => true,
            'default'   => null,
        ],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8_general_ci'
        ],
    ];

    public $records = [
        [
            'id' => 1,
            'aws_cognito_id' => '9a2b43e4-e002-4b1a-89b3-13a30e59c9ce',
            'aws_cognito_username' => 'test123',
            'email' => 'lorenzo@evilcorp.com.ar',
            'active' => 1,
            'role' => 'user',
            'first_name' => 'Pepito',
            'last_name' => 'Pepez',
            'created_at' => '2015-06-24 17:33:54',
            'modified_at' => '2015-06-24 17:33:54',
        ],
        [
            'id' => 2,
            'aws_cognito_id' => '1884ed93-6414-43c1-b537-5c40b659a2e0',
            'aws_cognito_username' => 'test_agent',
            'email' => 'lorenzo@izus.com.ar',
            'active' => 1,
            'role' => 'user',
            'first_name' => 'Test',
            'last_name' => 'Agent',
            'created_at' => '2015-06-24 17:33:54',
            'modified_at' => '2015-06-24 17:33:54',
        ],
        [
            'id' => 3,
            'aws_cognito_id' => '8148de39-44-41c63-5b713c5-04b59a6e20',
            'aws_cognito_username' => 'test_agent2',
            'email' => 'agent-2@test.com',
            'active' => 1,
            'role' => 'user',
            'first_name' => 'Test2',
            'last_name' => 'Agent2',
            'created_at' => '2015-06-24 17:33:54',
            'modified_at' => '2015-06-24 17:33:54',
        ],
    ];
}
