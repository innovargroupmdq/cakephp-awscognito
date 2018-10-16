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
            ->setMethods(['adminCreateUser'])
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $cognito_client->method('adminCreateUser')
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

        $behavior_mock = $this->getMockBuilder(AwsCognitoBehavior::class)
            ->setConstructorArgs([
                $this->table,
                ['createCognitoClient' => function(){ return $this->getMockCognitoClient(); }]
            ])
            ->setMethods(['createCognitoUser'])
            ->getMock();

        $behavior_mock
            ->expects($this->once())
            ->method('createCognitoUser')
            ->will($this->returnValue(true));

        $this->table->behaviors()->set('AwsCognito', $behavior_mock);

        $this->table->save($entity);
    }

    public function testBeforeSaveEnableDisableCognitoUser()
    {
        $entity = $this->table->find()->first();

        $behavior_mock = $this->getMockBuilder(AwsCognitoBehavior::class)
            ->setConstructorArgs([
                $this->table,
                ['createCognitoClient' => function(){ return $this->getMockCognitoClient(); }]
            ])
            ->setMethods(['disableCognitoUser', 'enableCognitoUser'])
            ->getMock();

        $behavior_mock
            ->expects($this->once())
            ->method('disableCognitoUser')
            ->will($this->returnValue(true));

        $behavior_mock
            ->expects($this->once())
            ->method('enableCognitoUser')
            ->will($this->returnValue(true));
        $this->table->behaviors()->set('AwsCognito', $behavior_mock);

        $entity->set('active', 0);
        $this->table->save($entity);

        $entity->set('active', 1);
        $this->table->save($entity);
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

        $this->table->delete($entity);
    }

    public function testChangeEmail()
    {
        $this->markTestIncomplete();
    }

    public function testResendInvitationEmail()
    {
        $this->markTestIncomplete();
    }

    public function testGetWithCognitoData()
    {
        $this->markTestIncomplete();
    }

    public function testResetCognitoPassword()
    {
        $this->markTestIncomplete();
    }

    public function testDeleteCognitoUser()
    {
        $this->markTestIncomplete();
    }

}
