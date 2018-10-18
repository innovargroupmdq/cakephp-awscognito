<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Model\Behavior;

use EvilCorp\AwsCognito\Model\Behavior\ImportApiUsersBehavior;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Test Case
 */
class ImportApiUsersBehaviorTest extends TestCase
{
    public $fixtures = [
        'plugin.EvilCorp/AwsCognito.api_users',
    ];

    public function setUp()
    {
        parent::setUp();
        $this->table = TableRegistry::get('EvilCorp/AwsCognito.ApiUsers');
        $this->Behavior = new ImportApiUsersBehavior($this->table);
    }

    public function tearDown()
    {
        unset($this->table, $this->Behavior);
        parent::tearDown();
    }

    public function testValidateManySuccess()
    {
        $rows = [
            [
                'first_name'           => 'Pepito',
                'last_name'            => 'Edited Last Name',
                'aws_cognito_username' => 'test123',
                'email'                => 'lorenzo@evilcorp.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => 'New',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'new@user.com.ar',
                'role'                 => 'user'
            ],
        ];
        $entities = $this->Behavior->validateMany($rows);
        foreach ($entities as $entity) {
            $this->assertEmpty($entity->getErrors());
        }
    }

    public function testValidateManyFailHasErrors()
    {
        $rows = [
            [
                'first_name'           => 'Pepito',
                'last_name'            => 'Edited Last Name',
                'aws_cognito_username' => 'test123',
                'email'                => 'lorenzo@evilcorp2.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => '',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'new@user.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => 'asdasd',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'new@user.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => '',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'agent-2@test.com',
                'role'                 => 'user'
            ],
        ];
        $entities = $this->Behavior->validateMany($rows);
        $this->assertEquals([
            'email' => [
                'CannotEditEmail' => 'The email cannot be directly modified'
            ]
        ], $entities[0]->getErrors());
        $this->assertEquals([
            'first_name' => [
                '_empty' => 'This field cannot be left empty'
            ]
        ], $entities[1]->getErrors());
        $this->assertEquals([
            'email' => [
                'duplicated' => 'This field is duplicated'
            ],
            'aws_cognito_username' => [
                'duplicated' => 'This field is duplicated'
            ],
        ], $entities[2]->getErrors());
        $this->assertEquals([
            'first_name' => [
                '_empty' => 'This field cannot be left empty'
            ],
            'email' => [
                '_isUnique' => 'Email already exists'
            ],
            'aws_cognito_username' => [
                'duplicated' => 'This field is duplicated'
            ],
        ], $entities[3]->getErrors());
    }

    public function testValidateManyMaxErrors()
    {
        $rows = [
            [
                'first_name'           => 'Pepito',
                'last_name'            => 'Edited Last Name',
                'aws_cognito_username' => 'test123',
                'email'                => 'lorenzo@evilcorp2.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => '',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'new@user.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => 'asdasd',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'new@user.com.ar',
                'role'                 => 'user'
            ],
            [
                'first_name'           => '',
                'last_name'            => 'User',
                'aws_cognito_username' => 'new.user',
                'email'                => 'agent-2@test.com',
                'role'                 => 'user'
            ],
        ];
        $max_errors = 2;
        $entities = $this->Behavior->validateMany($rows, $max_errors);

        $errors = 0;
        foreach ($entities as $entity) {
            if($entity->getErrors()) $errors++;
        }

        $this->assertEquals($max_errors, $errors);
    }

    public function testCsvDataToAssociativeArray()
    {
        $csv_data = [
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"robert.tito", "robert@tito.com", "Robert", "Tito", "user"',
        ];
        $expected = [
            [
                'aws_cognito_username' => 'pepe.pepez',
                'email' => 'pepe@pepez.com',
                'first_name' => 'Pepito',
                'last_name' => 'Pepez',
                'role' => 'user'
            ],
            [
                'aws_cognito_username' => 'pepe.pepez',
                'email' => 'pepe@pepez.com',
                'first_name' => 'Pepito',
                'last_name' => 'Pepez',
                'role' => 'user'
            ],
            [
                'aws_cognito_username' => 'robert.tito',
                'email' => 'robert@tito.com',
                'first_name' => 'Robert',
                'last_name' => 'Tito',
                'role' => 'user'
            ]
        ];

        $ass_array = $this->Behavior->csvDataToAssociativeArray(implode("\n", $csv_data));
        $this->assertEquals($expected, $ass_array);
    }

    public function testCsvDataToAssociativeArrayCustomFields()
    {
        $csv_data = [
            '"pepe.pepez", "pepe@pepez.com", "user"',
            '"pepe.pepez", "pepe@pepez.com", "user"',
            '"robert.tito", "robert@tito.com", "user"',
        ];
        $expected = [
            [
                'aws_cognito_username' => 'pepe.pepez',
                'email' => 'pepe@pepez.com',
                'role' => 'user'
            ],
            [
                'aws_cognito_username' => 'pepe.pepez',
                'email' => 'pepe@pepez.com',
                'role' => 'user'
            ],
            [
                'aws_cognito_username' => 'robert.tito',
                'email' => 'robert@tito.com',
                'role' => 'user'
            ]
        ];

        $headers = [
            'aws_cognito_username',
            'email',
            'role'
        ];

        $ass_array = $this->Behavior->csvDataToAssociativeArray(implode("\n", $csv_data), $headers);
        $this->assertEquals($expected, $ass_array);
    }
}
