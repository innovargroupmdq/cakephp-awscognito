<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller;

use EvilCorp\AwsCognito\Controller\ApiUsersController;
use Cake\TestSuite\IntegrationTestCase;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use EvilCorp\AwsCognito\Model\Behavior\AwsCognitoBehavior;

use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;

class ApiUsersControllerTest extends IntegrationTestCase
{

    public $fixtures = [
        'plugin.EvilCorp/AwsCognito.api_users',
    ];

    public function setUp()
    {
        Configure::write('AwsS3.local_only', true);
    }

    public function controllerSpy($event, $controller = null)
    {
        parent::controllerSpy($event, $controller);

        //mock CognitoClient
        $behavior = new AwsCognitoBehavior(
            $this->_controller->ApiUsers, [
                'createCognitoClient' => function(){
                    $cognito_client = $this->getMockBuilder(CognitoIdentityProviderClient::class)
                        ->disableOriginalConstructor()
                        ->disableOriginalClone()
                        ->disableArgumentCloning()
                        ->disallowMockingUnknownTypes()
                        ->getMock();
                    return $cognito_client;
                }
        ]);

        $this->_controller->ApiUsers->behaviors()->set('AwsCognito', $behavior);
    }

    public function testInitialize()
    {
        $this->get('/aws-cognito/api-users');

        $has_search = $this->_controller->components()->has('Prg');
        $this->assertTrue($has_search);

        $prg_config = $this->_controller->components()->get('Prg')->getConfig('actions');
        $this->assertTrue(in_array('index', $prg_config));


    }

}
