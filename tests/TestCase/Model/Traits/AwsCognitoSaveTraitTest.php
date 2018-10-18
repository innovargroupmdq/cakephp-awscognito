<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Model\Traits;

use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;
use EvilCorp\AwsCognito\Model\Behavior\AwsCognitoBehavior;

use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Aws\Result;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;


class AwsCognitoSaveTraitTest extends TestCase
{

	public $fixtures = [
		'plugin.EvilCorp/AwsCognito.api_users'
	];

	public function setUp()
	{
		parent::setUp();
		$this->ApiUsers = TableRegistry::get('EvilCorp/AwsCognito.ApiUsers');
	}

	public function tearDown()
	{
		unset($this->ApiUsers);
		TableRegistry::clear();
		parent::tearDown();
	}

	protected function getMockCognitoClient()
    {
        $cognito_client = $this->getMockBuilder(CognitoIdentityProviderClient::class)
            ->setMethods([
                'adminCreateUser',
                'adminUpdateUserAttributes',
                'adminDisableUser',
                'adminEnableUser',
                'adminGetUser',
                'adminResetUserPassword',
                'adminDeleteUser'
            ])
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();
        return $cognito_client;
    }

	public function testSaveManySuccess()
	{

		$entities = $this->ApiUsers->newEntities([
			[
				'aws_cognito_id'       => 'testid',
				'aws_cognito_username' => 'new.validusername',
				'email'                => 'newvalid@email.com.ar',
				'role'                 => 'user'
	        ],
	        [
				'aws_cognito_id'       => 'testid2',
				'aws_cognito_username' => 'new.validusername2',
				'email'                => 'newvalid2@email.com.ar',
				'role'                 => 'user'
	        ],
	        [
				'aws_cognito_id'       => 'testid3',
				'aws_cognito_username' => 'new.validusernam3e',
				'email'                => 'newvalid3@email.com.ar',
				'role'                 => 'user'
	        ]
		], [
            'validate' => false,
            'accessibleFields' => [
				'email'                => true,
				'aws_cognito_username' => true,
				'aws_cognito_id'       => true,
				'role'                 => true
            ]
        ]);

        $behavior_mock = new AwsCognitoBehavior(
            $this->ApiUsers, [
                'createCognitoClient' => function(){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                    	->expects($this->exactly(3))
                        ->method('adminCreateUser')
                        ->will($this->returnCallback(function($options){
                            return new Result([
                                'User' => [
                                    'Attributes' => array_merge($options['UserAttributes'], [
                                        [
                                            'Name' => 'sub',
                                            'Value' => uniqid()
                                        ]
                                    ]),
                                    'Enabled'    => true,
                                    'Username'   => $options['Username'],
                                ]
                            ]);
                        }));
                    return $cognito_client;
                }
        ]);
        $this->ApiUsers->behaviors()->set('AwsCognito', $behavior_mock);

        $entities = $this->ApiUsers->saveMany($entities);

        $this->assertNotFalse($entities);

        foreach ($entities as $entity) {
	        $this->assertNotEmpty($entity->get('aws_cognito_id'));
	        $this->assertNotEmpty($entity->get('aws_cognito_username'));
        }

	}


	public function testSaveManyFailCallDeleteCognitoUser()
	{
		$entities = $this->ApiUsers->newEntities([
			[
				'aws_cognito_id'       => 'testid',
				'aws_cognito_username' => 'new.validusername',
				'email'                => 'newvalid@email.com.ar',
				'role'                 => 'user',
				'first_name'           => 'name',
				'last_name'            => 'name',
	        ],
	        [
				'aws_cognito_id'       => 'testid2',
				'aws_cognito_username' => 'new.validusername2',
				'email'                => 'newvalid2@email.com.ar',
				'role'                 => 'user'
	        ],
	        [
				'aws_cognito_id'       => 'testid3',
				'aws_cognito_username' => 'new.validusernam3e',
				'email'                => 'newvalid3@email.com.ar',
				'role'                 => 'user'
	        ]
		], [
            'validate' => true,
            'accessibleFields' => [
				'email'                => true,
				'aws_cognito_username' => true,
				'aws_cognito_id'       => true,
				'role'                 => true
            ]
        ]);

        $behavior_mock = new AwsCognitoBehavior(
            $this->ApiUsers, [
                'createCognitoClient' => function(){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                    	->expects($this->once())
                        ->method('adminCreateUser')
                        ->will($this->returnCallback(function($options){
                            return new Result([
                                'User' => [
                                    'Attributes' => array_merge($options['UserAttributes'], [
                                        [
                                            'Name' => 'sub',
                                            'Value' => uniqid()
                                        ]
                                    ]),
                                    'Enabled'    => true,
                                    'Username'   => $options['Username'],
                                ]
                            ]);
                        }));
                    $cognito_client
                    	->expects($this->exactly(3))
                        ->method('adminDeleteUser')
                        ->will($this->returnValue(null));
                    return $cognito_client;
                }
        ]);
        $this->ApiUsers->behaviors()->set('AwsCognito', $behavior_mock);

        $result = $this->ApiUsers->saveMany($entities);

        $this->assertFalse($result);

        $this->assertEmpty($entities[0]->getErrors());
        $this->assertNotEmpty($entities[1]->getErrors());
        $this->assertNotEmpty($entities[2]->getErrors());

	}


}