<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller\Traits;

use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use PHPUnit_Framework_MockObject_RuntimeException;

use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Result;
use EvilCorp\AwsCognito\Model\Behavior\AwsCognitoBehavior;

use Cake\Network\Session;
use Cake\Network\Request;

abstract class BaseTraitTest extends TestCase
{
    public $fixtures = [
        'plugin.EvilCorp/AwsCognito.api_users',
        'plugin.EvilCorp/AwsCognito.users',
    ];

    public $traitClassName = '';
    public $traitMockMethods = [];

    public function setUp()
    {
        parent::setUp();
        $this->ApiUsers = TableRegistry::get(ApiUsersTable::class);
        $this->ApiUsers->setAlias('ApiUsers');
        try {
            //mock trait
            $this->Trait = $this->getMockBuilder($this->traitClassName)
                    ->setMethods($this->traitMockMethods)
                    ->getMockForTrait();

            //mock CognitoClient
            $behavior = new AwsCognitoBehavior(
                $this->ApiUsers, [
                    'createCognitoClient' => function(){
                        $cognito_client = $this->getMockBuilder(CognitoIdentityProviderClient::class)
                            ->setMethods([
                                'adminCreateUser',
                                'adminUpdateUserAttributes',
                                'adminDisableUser',
                                'adminEnableUser',
                                'adminGetUser',
                                'adminDeleteUser'
                            ])
                            ->disableOriginalConstructor()
                            ->disableOriginalClone()
                            ->disableArgumentCloning()
                            ->disallowMockingUnknownTypes()
                            ->getMock();
                        $cognito_client->expects($this->any())
                            ->method('adminUpdateUserAttributes')
                            ->will($this->returnValue(null));
                        $cognito_client->expects($this->any())
                            ->method('adminEnableUser')
                            ->will($this->returnValue(null));
                        $cognito_client->expects($this->any())
                            ->method('adminDisableUser')
                            ->will($this->returnValue(null));
                        $cognito_client->expects($this->any())
                            ->method('adminDeleteUser')
                            ->will($this->returnValue(null));
                        $cognito_client->expects($this->any())
                            ->method('adminGetUser')
                            ->will($this->returnValue(new Result([
                                'Enabled'    => true,
                                'UserAttributes' => [
                                    [
                                        'Name' => 'sub',
                                        'Value' => 'a3e40565-4cf0-4033-b7c9-da973533a9ef'
                                    ],
                                    [
                                        'Name' => 'email',
                                        'Value' => 'knowingness@tebeth.org'
                                    ],
                                    [
                                        'Name' => 'email_verified',
                                        'Value' => 'true'
                                    ],
                                ],
                                'Username'   => 'test_username',
                                'UserStatus' => 'CONFIRMED'
                            ])));
                        $cognito_client->expects($this->any())
                            ->method('adminCreateUser')
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
            $this->ApiUsers->behaviors()->set('AwsCognito', $behavior);

            //set table for trait
            $this->Trait->ApiUsers = $this->ApiUsers;

        } catch (PHPUnit_Framework_MockObject_RuntimeException $ex) {
            debug($ex);
            $this->fail("Unit tests extending BaseTraitTest should declare the trait class name in the \$traitClassName variable before calling setUp()");
        }

    }

    public function tearDown()
    {
        unset($this->ApiUsers, $this->Trait);
        parent::tearDown();
        TableRegistry::clear();
    }

    protected function _mockSession($attributes)
    {
        $session = new Session();

        foreach ($attributes as $field => $value) {
            $session->write($field, $value);
        }

        $this->Trait->request
            ->expects($this->any())
            ->method('session')
            ->willReturn($session);
    }

    protected function _mockRequestGet($withSession = false)
    {
        $methods = ['is', 'referer', 'getData', 'getQueryParams'];

        if ($withSession) {
            $methods[] = 'session';
        }

        $this->Trait->request = $this->getMockBuilder(Request::class)
                ->setMethods($methods)
                ->getMock();
        $this->Trait->request->expects($this->any())
                ->method('is')
                ->will($this->returnValue(false));
    }

    protected function _mockFlash()
    {
        $this->Trait->Flash = $this->getMockBuilder('Cake\Controller\Component\FlashComponent')
                ->setMethods(['error', 'success'])
                ->disableOriginalConstructor()
                ->getMock();
    }

    protected function _mockRequestPost($with = 'post')
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Network\Request')
                ->setMethods(['is', 'getData', 'allow'])
                ->getMock();
        $this->Trait->request->expects($this->any())
                ->method('is')
                ->with($with)
                ->will($this->returnValue(true));
    }

    /**
     * mock utility
     *
     * @param Event $event event
     * @return void
     */
    protected function _mockDispatchEvent(Event $event = null)
    {
        if (is_null($event)) {
            $event = new Event('cool-name-here');
        }
        $this->Trait->expects($this->any())
                ->method('dispatchEvent')
                ->will($this->returnValue($event));
    }
}
