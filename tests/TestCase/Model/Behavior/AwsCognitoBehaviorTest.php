<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Model\Behavior;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use EvilCorp\AwsCognito\Model\Behavior\AwsCognitoBehavior;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Core\Configure;
use Cake\Validation\Validator;
use Cake\Event\Event;
use Cake\ORM\RulesChecker;
use ArrayObject;
use Aws\Result;
use Cake\Datasource\Exception\RecordNotFoundException;

/**
 * Test Case
 */
class AwsCognitoBehaviorTest extends TestCase
{
    public $fixtures = [
        'plugin.EvilCorp/AwsCognito.api_users',
    ];

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

    public function setUp()
    {
        Configure::write('ApiUsers.use_aws_s3', false);

        parent::setUp();
        $this->table = TableRegistry::get('EvilCorp/AwsCognito.ApiUsers');
        $this->Behavior = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function(){ return $this->getMockCognitoClient(); }
        ]);
    }

    public function tearDown()
    {
        unset($this->table, $this->Behavior);
        parent::tearDown();
    }

    public function testValidationResendInvitationEmail()
    {
        $validator = new Validator();
        $this->Behavior->validationResendInvitationEmail($validator);
        $this->assertTrue($validator->isPresenceRequired('email', false));
        $this->assertTrue($validator->isPresenceRequired('email', true));
    }

    public function testValidationChangeEmail()
    {
        $validator = new Validator();
        $this->Behavior->validationChangeEmail($validator);
        $this->assertTrue($validator->isPresenceRequired('email', false));
        $this->assertTrue($validator->isPresenceRequired('email', true));
    }

    public function testBuildValidator()
    {
        $validator = $this->Behavior->buildValidator(new Event('eventName'), new Validator(), 'default');

        $this->assertTrue($validator->isPresenceRequired('aws_cognito_username', true));
        $this->assertFalse($validator->isPresenceRequired('aws_cognito_username', false));
        $this->assertNotEmpty($validator->field('aws_cognito_username')->rule('notBlank'));

        $this->assertNotEmpty($validator->field('email')->rule('email'));
        $this->assertNotEmpty($validator->field('email')->rule('notBlank'));

        $this->assertTrue($validator->isPresenceRequired('email', true));
        $this->assertFalse($validator->isPresenceRequired('email', false));
        $this->assertNotEmpty($validator->field('email')->rule('emailImmutable'));

        $validator = $this->Behavior->buildValidator(new Event('eventName'), new Validator(), 'changeEmail');

        $this->assertFalse($validator->isPresenceRequired('email', true));
        $this->assertFalse($validator->isPresenceRequired('email', false));
        $this->assertEmpty($validator->field('email')->rule('emailImmutable'));

        $validator = $this->Behavior->buildValidator(new Event('eventName'), new Validator(), 'resendInvitationEmail');

        $this->assertFalse($validator->isPresenceRequired('email', true));
        $this->assertFalse($validator->isPresenceRequired('email', false));
        $this->assertEmpty($validator->field('email')->rule('emailImmutable'));
    }

    public function testBuildRulesIsUnique()
    {
        $rules = $this->Behavior->buildRules(new Event('eventName'), new RulesChecker());
        $entity = $this->table->newEntity([
            'aws_cognito_username' => 'test123',
            'email' => 'lorenzo@evilcorp.com.ar'
        ], [
            'validate' => false,
            'accessibleFields' => ['email' => true, 'aws_cognito_username' => true]
        ]);
        $result = $rules->checkCreate($entity, ['repository' => $this->table ]);
        $this->assertFalse($result);
        $this->assertArrayHasKey('_isUnique', $entity->getError('email'));
        $this->assertArrayHasKey('_isUnique', $entity->getError('aws_cognito_username'));
    }

    public function testBuildRulesCannotEditCognitoUsername()
    {
        $rules = $this->Behavior->buildRules(new Event('eventName'), new RulesChecker());
        $entity = $this->table->find()->first();
        $entity = $this->table->patchEntity($entity, [
            'aws_cognito_username' => 'new cognito username',
        ], [
            'validate' => false,
            'accessibleFields' => ['aws_cognito_username' => true]
        ]);
        $result = $rules->checkUpdate($entity, ['repository' => $this->table ]);
        $this->assertFalse($result);
        $this->assertArrayHasKey('CannotEditCognitoUsername', $entity->getError('aws_cognito_username'));
    }

    public function testBuildRulesCannotEditCognitoId()
    {
        $rules = $this->Behavior->buildRules(new Event('eventName'), new RulesChecker());
        $entity = $this->table->find()->first();
        $entity = $this->table->patchEntity($entity, [
            'aws_cognito_id' => 'new cognito id',
        ], [
            'validate' => false,
            'accessibleFields' => ['aws_cognito_id' => true]
        ]);
        $result = $rules->checkUpdate($entity, ['repository' => $this->table ]);
        $this->assertFalse($result);
        $this->assertArrayHasKey('CannotEditCognitoId', $entity->getError('aws_cognito_id'));
    }

    public function testBeforeSaveCreateCognitoUser()
    {
        $entity = $this->table->newEntity([
            'aws_cognito_id' => 'testid',
            'aws_cognito_username' => 'new.validusername',
            'email' => 'newvalid@email.com.ar',
            'role' => 'user'
        ], [
            'validate' => false,
            'accessibleFields' => [
                'email' => true,
                'aws_cognito_username' => true,
                'aws_cognito_id' => true,
                'role' => true
            ]
        ]);

        $entity->set('active', 1);

        $behavior_mock = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminCreateUser')
                        ->with($this->identicalTo([
                            'DesiredDeliveryMediums' => ['EMAIL'],
                            'ForceAliasCreation'     => false,
                            'UserAttributes' => [
                                [
                                'Name' => 'email',
                                'Value' => $entity->email,
                                ],
                                [
                                'Name' => 'email_verified',
                                'Value' => 'true',
                                ],
                            ],
                            'UserPoolId' => $this->Behavior->getConfig('UserPool.id'),
                            'Username'   => $entity->aws_cognito_username,
                        ]))
                        ->will($this->returnCallback(function($options){
                            return new Result([
                                'User' => [
                                    'Attributes' => array_merge($options['UserAttributes'], [
                                        [
                                            'Name' => 'sub',
                                            'Value' => 'a2d1c8af-c6b3-495a-a9f7-f4b8f4a8aa9b'
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

        $this->table->behaviors()->set('AwsCognito', $behavior_mock);

        $entity = $this->table->save($entity);

        $this->assertNotFalse($entity);

        $this->assertNotEmpty($entity->get('aws_cognito_id'));
        $this->assertNotEmpty($entity->get('aws_cognito_username'));

    }

    public function testBeforeSaveEnableDisableCognitoUser()
    {
        $entity = $this->table->find()->first();

        $behavior_mock = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminDisableUser')
                        ->with($this->identicalTo([
                            'UserPoolId' => $this->Behavior->getConfig('UserPool.id'),
                            'Username'   => $entity->aws_cognito_username,
                        ]))
                        ->will($this->returnValue(null));

                    $cognito_client
                        ->expects($this->once())
                        ->method('adminEnableUser')
                        ->with($this->identicalTo([
                            'UserPoolId' => $this->Behavior->getConfig('UserPool.id'),
                            'Username'   => $entity->aws_cognito_username,
                        ]))
                        ->will($this->returnValue(null));
                    return $cognito_client;
                }
        ]);
        $this->table->behaviors()->set('AwsCognito', $behavior_mock);

        $entity->set('active', 0);
        $entity = $this->table->save($entity);
        $this->assertNotFalse($entity);
        $this->assertEquals(0, $entity->get('active'));

        $entity->set('active', 1);
        $entity = $this->table->save($entity);
        $this->assertNotFalse($entity);
        $this->assertEquals(1, $entity->get('active'));
    }

    public function testBeforeDelete()
    {
        //tests deleteCognitoUser is called when deleting entity
        $entity  = $this->table->find()->first();

        $behavior_mock = $this->getMockBuilder(AwsCognitoBehavior::class)
            ->setConstructorArgs([
                $this->table,
                ['createCognitoClient' => function(){ return $this->getMockCognitoClient(); }]
            ])
            ->setMethods(['deleteCognitoUser'])
            ->getMock();

        $behavior_mock
            ->expects($this->once())
            ->method('deleteCognitoUser')
            ->will($this->returnValue(true));

        $this->table->behaviors()->set('AwsCognito', $behavior_mock);

        $result = $this->table->delete($entity);
        $this->assertTrue($result);

        //properly deleted
        $this->expectException(RecordNotFoundException::class);
        $this->table->get($entity->id);
    }

    public function testChangeEmailFailUserNotSaved()
    {
        $entity = $this->table->newEntity([
            'aws_cognito_id' => 'testid',
            'aws_cognito_username' => 'new.validusername',
            'email' => 'newvalid@email.com.ar',
            'role' => 'user'
        ], [
            'validate' => false,
            'accessibleFields' => [
                'email' => true,
                'aws_cognito_username' => true,
                'aws_cognito_id' => true,
                'role' => true
            ]
        ]);
        $require_verification = true;
        $new_email = 'new.email@evilcorp.com.ar';

        //requires entity to be saved
        $this->expectExceptionMessage(__d('EvilCorp/AwsCognito', 'Cannot edit email of an nonexistent user.'));
        $this->Behavior->changeEmail($entity, $new_email, $require_verification);
    }

    public function testChangeEmailFailMissingUsername()
    {
        $require_verification = true;
        $new_email = 'new.email@evilcorp.com.ar';

        //requires entity to have cognito username
        $entity = $this->table->find()->first();
        $entity->set('aws_cognito_username', null);
        $this->expectExceptionMessage(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        $this->Behavior->changeEmail($entity, $new_email, $require_verification);
    }

    public function testChangeEmailSuccess()
    {
        $require_verification = true;
        $new_email = 'new.email@evilcorp.com.ar';
        $entity = $this->table->find()->first();

        //changes email and calls CognitoClient->adminUpdateUserAttributes
        $this->Behavior = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity, $require_verification, $new_email){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminUpdateUserAttributes')
                        ->with($this->identicalTo([
                            'UserAttributes' => [
                                [
                                    'Name' => 'email',
                                    'Value' => $new_email
                                ],
                                [
                                    'Name' => 'email_verified',
                                    'Value' => $require_verification ? 'false' : 'true'
                                ]
                            ],
                            'UserPoolId'     => $this->Behavior->getConfig('UserPool.id'),
                            'Username'       => $entity->aws_cognito_username,
                        ]))
                        ->will($this->returnValue(null));
                    return $cognito_client;
                }
        ]);
        $result = $this->Behavior->changeEmail($entity, $new_email, $require_verification);
        $this->assertTrue($result);
        $this->assertEquals($new_email, $entity->email);
        $this->assertFalse($entity->isDirty('email'));
    }

    public function testResendInvitationEmailFailUserNotSaved()
    {
        $entity = $this->table->newEntity([
            'aws_cognito_id' => 'testid',
            'aws_cognito_username' => 'new.validusername',
            'email' => 'newvalid@email.com.ar',
            'role' => 'user'
        ], [
            'validate' => false,
            'accessibleFields' => [
                'email' => true,
                'aws_cognito_username' => true,
                'aws_cognito_id' => true,
                'role' => true
            ]
        ]);
        $new_email = 'new.email@evilcorp.com.ar';

        //requires entity to be saved
        $this->expectExceptionMessage(__d('EvilCorp/AwsCognito', 'You must create the entity before trying to resend the invitation email'));
        $this->Behavior->resendInvitationEmail($entity, $new_email);
    }

    public function testResendInvitationEmail()
    {
        $new_email = 'new.email@evilcorp.com.ar';
        $entity = $this->table->find()->first();

        //changes email and calls CognitoClient->adminCreateUser
        $this->Behavior = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity, $new_email){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminCreateUser')
                        ->with($this->identicalTo([
                            'DesiredDeliveryMediums' => ['EMAIL'],
                            'ForceAliasCreation'     => false,
                            'UserAttributes' => [
                                [
                                'Name' => 'email',
                                'Value' => $new_email,
                                ],
                                [
                                'Name' => 'email_verified',
                                'Value' => 'true',
                                ],
                            ],
                            'UserPoolId'    => $this->Behavior->getConfig('UserPool.id'),
                            'Username'      => $entity->aws_cognito_username,
                            'MessageAction' => 'RESEND'
                        ]))
                        ->will($this->returnCallback(function($options){
                            return new Result([
                                'User' => [
                                    'Attributes' => array_merge($options['UserAttributes'], [
                                        [
                                            'Name' => 'sub',
                                            'Value' => 'a2d1c8af-c6b3-495a-a9f7-f4b8f4a8aa9b'
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
        $result = $this->Behavior->resendInvitationEmail($entity, $new_email);
        $this->assertTrue($result);
        $this->assertEquals($new_email, $entity->email);
        $this->assertFalse($entity->isDirty('email'));
    }

    public function testGetWithCognitoDataSuccess()
    {
        $entity = $this->table->find()->first();

        //mock cognitoClient->adminGetUser
        $this->Behavior = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity){
                    $cognito_client = $this->getMockCognitoClient();
                    $call_result = new Result([
                        'Enabled'    => true,
                        'UserAttributes' => [
                            [
                                'Name' => 'sub',
                                'Value' => $entity->aws_cognito_id
                            ],
                            [
                                'Name' => 'email',
                                'Value' => $entity->email
                            ],
                            [
                                'Name' => 'email_verified',
                                'Value' => 'true'
                            ],
                        ],
                        'Username'   => $entity->aws_cognito_username,
                        'UserStatus' => 'CONFIRMED'
                    ]);
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminGetUser')
                        ->with($this->identicalTo([
                            'UserPoolId' => $this->Behavior->getConfig('UserPool.id'),
                            'Username'   => $entity->aws_cognito_username
                        ]))
                        ->will($this->returnValue($call_result));
                    return $cognito_client;
                }
        ]);

        //finds user, compare it to normal find result
        $id = $entity->id;
        $options = [];
        $user = $this->table->get($id, $options);
        $user_with_cognito = $this->Behavior->getWithCognitoData($id, $options);

        $this->assertEquals($user->id, $user_with_cognito->id);

        //check for extra attributes
        $this->assertTrue($user_with_cognito->get('aws_cognito_synced'));
        $this->assertEquals([
            'sub'            => $user->aws_cognito_id,
            'email'          => $user->email,
            'email_verified' => true,
        ], $user_with_cognito->get('aws_cognito_attributes'));
        $this->assertEquals([
            'code'        => 'CONFIRMED',
            'title'       => __d('EvilCorp/AwsCognito', 'Confirmed'),
            'description' => __d('EvilCorp/AwsCognito', 'The user account is confirmed and the user can sign in.'),
        ], $user_with_cognito->get('aws_cognito_status'));
    }

    public function testResetCognitoPasswordFailMissingUsername()
    {
        //requires entity to have cognito username
        $entity = $this->table->find()->first();
        $entity->set('aws_cognito_username', null);
        $this->expectExceptionMessage(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        $this->Behavior->resetCognitoPassword($entity);
    }

    public function testResetCognitoPasswordSuccess()
    {
        $entity = $this->table->find()->first();

        //mock CognitoClient->adminResetUserPassword
        $this->Behavior = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminResetUserPassword')
                        ->with($this->identicalTo([
                            'UserPoolId' => $this->Behavior->getConfig('UserPool.id'),
                            'Username'   => $entity->aws_cognito_username
                        ]))
                        ->will($this->returnValue(null));
                    return $cognito_client;
                }
        ]);

        $result = $this->Behavior->resetCognitoPassword($entity);
        $this->assertTrue($result);
    }

    public function testDeleteCognitoUserFailMissingUsername()
    {
        //requires entity to have cognito username
        $entity = $this->table->find()->first();
        $entity->set('aws_cognito_username', null);
        $this->expectExceptionMessage(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        $this->Behavior->resetCognitoPassword($entity);
    }

    public function testDeleteCognitoUserSuccess()
    {
        $entity = $this->table->find()->first();

        //mock CognitoClient->adminDeleteUser
        $this->Behavior = new AwsCognitoBehavior(
            $this->table, [
                'createCognitoClient' => function() use ($entity){
                    $cognito_client = $this->getMockCognitoClient();
                    $cognito_client
                        ->expects($this->once())
                        ->method('adminDeleteUser')
                        ->with($this->identicalTo([
                            'UserPoolId' => $this->Behavior->getConfig('UserPool.id'),
                            'Username'   => $entity->aws_cognito_username
                        ]))
                        ->will($this->returnValue(null));
                    return $cognito_client;
                }
        ]);

        $result = $this->Behavior->deleteCognitoUser($entity);
        $this->assertTrue($result);
    }

}
