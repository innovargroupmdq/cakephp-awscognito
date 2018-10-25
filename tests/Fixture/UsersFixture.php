<?php
namespace EvilCorp\AwsCognito\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 *
 */
class UsersFixture extends TestFixture
{

    public $fields = [
        'id' => [
            'type'      => 'uuid',
            'length'    => null,
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
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8_general_ci'
        ],
    ];

    public $records = [
    ];
}
