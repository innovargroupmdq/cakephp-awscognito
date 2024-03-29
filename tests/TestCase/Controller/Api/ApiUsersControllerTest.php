<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller\Api;

use EvilCorp\AwsCognito\Controller\Api\ApiUsersController;
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
        parent::setUp();

        Configure::write('Error', [
            'errorLevel' => E_ALL,
            'exceptionRenderer' => 'EvilCorp\AwsApiGateway\Error\ApiExceptionRenderer',
            'skipLog' => [],
            'log' => true,
            'trace' => true,
        ]);
        Configure::write('AwsS3.local_only', true);
    }

    public function tearDown()
    {
        parent::tearDown();

        //empty the uploads folder
        $uploads_folder_path = ROOT . DS . 'webroot' . DS . 'files';
        $uploads_folder = new Folder($uploads_folder_path);
        $removed = $uploads_folder->delete();
    }

    public function controllerSpy($event, $controller = null)
    {
        parent::controllerSpy($event, $controller);

        //mock CognitoClient
        $behavior = new AwsCognitoBehavior(
            $this->_controller->ApiUsers, [
                'createCognitoClient' => function(){
                    $cognito_client = $this->getMockBuilder(CognitoIdentityProviderClient::class)
                        ->setMethods([
                            'adminUpdateUserAttributes',
                            'adminDisableUser',
                            'adminEnableUser'
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
                    return $cognito_client;
                }
        ]);

        $this->_controller->ApiUsers->behaviors()->set('AwsCognito', $behavior);
    }

    protected function authorizeApiUser($content_length = false)
    {
        $api_id = 'test';

        Configure::write('AwsApiGateway.api_id', $api_id);

        $token = 'eyJraWQiOiJQdlB3RHRaeFp0SjFTSHJnZzA3Um92QzRnN1ZPcTFJQ2RyRWtWa1FhcFFFPSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiI5YTJiNDNlNC1lMDAyLTRiMWEtODliMy0xM2EzMGU1OWM5Y2UiLCJhdWQiOiI2aGdpZTFkaGtzbjN2c212ZGJsYXVsbGlkNSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJldmVudF9pZCI6Ijg4YmE5NjZiLTQ5NzktMTFlOC1iNzhlLWI1ZDY5NTk0MDZiYSIsInRva2VuX3VzZSI6ImlkIiwiYXV0aF90aW1lIjoxNTI0NzY0Njk2LCJpc3MiOiJodHRwczpcL1wvY29nbml0by1pZHAudXMtZWFzdC0xLmFtYXpvbmF3cy5jb21cL3VzLWVhc3QtMV9yam96MUhPYVIiLCJjb2duaXRvOnVzZXJuYW1lIjoidGVzdDEyMyIsImV4cCI6MTUyNDc2ODI5NiwiaWF0IjoxNTI0NzY0Njk3LCJlbWFpbCI6ImxvcmVuem9AZXZpbGNvcnAuY29tLmFyIn0.V6pXRtIsSJnUTuhTFcdwPvgTutpOqDDcbZHh9IHYtUUamA6B1hOsphR2BsQL9SGmMO0CyA9WfUEZCfsO7mnD9KfQYYigoXigbyS8bRUP_zS_CCHWTW2BaKQgV_ZHerZJO_9W_D6YcW4sMcPU2dweZkDA3hHvctN_turQhV-RokdbE7CdZQHIkY0kHt0vUSaU7gINNOn1Ovr_ZCmRvCjU93LH4fU1Erh0FP8DQC7BOxQtvftsXkF-jjmI_asmRyWNwAYP2OLDgRD9dRE7KxXGk5e5ppUt3AZfXBEjG61qjtQhQiXY-PS7dpCRji6STO8l34xpSi3sqqp9DPknVZhakw';

        $headers = [
            'Accept'                   => 'application/json',
            'Content-Type'             => 'application/json',
            'X-Amzn-Apigateway-Api-Id' => $api_id,
            'Authorization'            => $token,
        ];

        if($content_length){
            $headers['Content-Length'] = $content_length;
        }

        $this->configRequest([
            'headers' => $headers
        ]);
    }

    public function testProfile()
    {
        $this->authorizeApiUser();
        $this->get('/aws-cognito/api/api-users/profile');
        $this->assertResponseCode(200);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);

        $this->assertContains('data', array_keys($json_response));

        $this->assertArrayHasKey('email', $json_response['data']);
        $this->assertEquals($this->_controller->Auth->user('email'), $json_response['data']['email']);

        $this->assertNotEmpty($json_response['data']['username']);
        $this->assertNotEmpty($json_response['data']['role']);
        $this->assertNotEmpty($json_response['data']['first_name']);
        $this->assertNotEmpty($json_response['data']['last_name']);
    }


    public function testEditProfileSuccess()
    {
        $this->authorizeApiUser();

        $data = [
            'first_name' => 'edited first name',
            'last_name'  => 'edited last name',
        ];

        $this->patch('/aws-cognito/api/api-users/editProfile', json_encode($data));
        $this->assertResponseCode(200);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);

        $this->assertContains('data', array_keys($json_response));

        $this->assertArrayHasKey('email', $json_response['data']);
        $this->assertEquals($this->_controller->Auth->user('email'), $json_response['data']['email']);
        $this->assertNotEmpty($json_response['data']['username']);

        $this->assertEquals($data['first_name'], $json_response['data']['first_name']);
        $this->assertEquals($data['last_name'], $json_response['data']['last_name']);
    }

    public function testEditProfileFailInvalidFields()
    {
        $this->authorizeApiUser();

        $data = [
            'aws_cognito_username' => 'edited username',
            'email'  => 'edited@email.com',
            'active' => 0,
            'role' => 'test123',
        ];

        $this->patch('/aws-cognito/api/api-users/editProfile', $data);
        $this->assertResponseCode(200);

        $user_id = $this->_controller->Auth->user('id');

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);

        $this->assertContains('data', array_keys($json_response));

        $this->assertArrayHasKey('email', $json_response['data']);
        $this->assertEquals($this->_controller->Auth->user('email'), $json_response['data']['email']);
        $this->assertNotEmpty($json_response['data']['username']);

        $this->assertNotEquals($data['aws_cognito_username'], $json_response['data']['username']);
        $this->assertNotEquals($data['email'], $json_response['data']['email']);
        $this->assertNotEquals($data['role'], $json_response['data']['role']);

        $deactivated_user = TableRegistry::get('ApiUsers')->find()
            ->where([
                'ApiUsers.id' => $user_id,
                'ApiUsers.active' => $data['active']
            ]);

        $this->assertTrue($deactivated_user->isEmpty());
    }

    public function testEditProfileFailValidation()
    {
        $this->authorizeApiUser();

        $data = [
            'first_name' => '',
            'last_name'  => '',
        ];

        $this->patch('/aws-cognito/api/api-users/editProfile', json_encode($data));
        $this->assertResponseCode(422);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);

        $this->assertContains('errors', array_keys($json_response));


        $expected_errors = [
            'first_name' => [
                [
                    'code' => 'Empty',
                    'message' => 'This field cannot be left empty'
                ]
            ],
            'last_name' => [
                [
                    'code' => 'Empty',
                    'message' => 'This field cannot be left empty'
                ]
            ]
        ];

        $this->assertEquals($expected_errors, $json_response['errors']);
    }

    public function testChangeEmailSuccess()
    {
        $this->authorizeApiUser();

        $data = ['email' => 'edited@email.com'];

        $this->put('/aws-cognito/api/api-users/changeEmail', json_encode($data));
        $this->assertResponseCode(200);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);

        $this->assertContains('data', array_keys($json_response));

        $this->assertArrayHasKey('email', $json_response['data']);
        $this->assertNotEmpty($json_response['data']['email']);

        $this->assertEquals($data['email'], $json_response['data']['email']);
    }

    public function testChangeEmailFailValidation()
    {
        $this->authorizeApiUser();

        $data = ['email' => 'invalid email'];

        $this->put('/aws-cognito/api/api-users/changeEmail', json_encode($data));
        $this->assertResponseCode(422);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);

        $this->assertContains('errors', array_keys($json_response));

        $this->assertArrayHasKey('email', $json_response['errors']);
        $expected_error = [
            [
                'code' => 'Email',
                'message' => 'This field must be a valid email address'
            ]
        ];
        $this->assertEquals($expected_error, $json_response['errors']['email']);
    }

    public function testUploadAvatarSuccess()
    {
        $filepath = TESTS . 'Assets' . DS . 'test_image.jpg';
        $image_file = file_get_contents($filepath);
        $content_length = filesize($filepath);

        $this->assertNotFalse($image_file);
        $this->assertTrue(is_integer($content_length));

        $this->authorizeApiUser($content_length);

        $this->put('/aws-cognito/api/api-users/uploadAvatar', $image_file);
        $this->assertResponseCode(200);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);
        $this->assertContains('data', array_keys($json_response));

        $this->assertArrayHasKey('avatar', $json_response['data']);
        $this->assertNotEmpty($json_response['data']['avatar']);

        $this->assertEquals($image_file, file_get_contents(WWW_ROOT . $json_response['data']['avatar']));
    }

    public function testUploadAvatarFailContentLength()
    {
        $filepath = TESTS . 'Assets' . DS . 'test_image.jpg';
        $image_file = file_get_contents($filepath);

        $this->assertNotFalse($image_file);
        $this->authorizeApiUser();

        $this->put('/aws-cognito/api/api-users/uploadAvatar', $image_file);
        $this->assertResponseCode(422);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);
        $this->assertContains('errors', array_keys($json_response));

        $this->assertArrayHasKey('avatar', $json_response['errors']);
        $expected_error = [
            [
                'code' => 'FileCompletedUpload',
                'message' => 'This file could not be uploaded completely'
            ]
        ];
        $this->assertEquals($expected_error, $json_response['errors']['avatar']);
    }

    public function testUploadAvatarFailBrokenFile()
    {
        $filepath = TESTS . 'Assets' . DS . 'test_image.jpg';
        $image_file = file_get_contents($filepath);
        $this->assertNotFalse($image_file);

        //breaking the file
        $image_file = substr($image_file, 0, strlen($image_file) / 2 );

        $content_length = filesize($filepath);
        $this->assertTrue(is_integer($content_length));

        $this->authorizeApiUser($content_length);

        $this->put('/aws-cognito/api/api-users/uploadAvatar', $image_file);
        $this->assertResponseCode(422);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);
        $this->assertContains('errors', array_keys($json_response));

        $this->assertArrayHasKey('avatar', $json_response['errors']);
        $expected_error = [
            [
                'code' => 'FileCompletedUpload',
                'message' => 'This file could not be uploaded completely'
            ]
        ];
        $this->assertEquals($expected_error, $json_response['errors']['avatar']);
    }

    public function testUploadAvatarFailInvalidMimeType()
    {
        $filepath = TESTS . 'Assets' . DS . 'test_text_file.txt';
        $image_file = file_get_contents($filepath);
        $content_length = filesize($filepath);

        $this->assertNotFalse($image_file);
        $this->assertTrue(is_integer($content_length));

        $this->authorizeApiUser($content_length);

        $this->put('/aws-cognito/api/api-users/uploadAvatar', $image_file);
        $this->assertResponseCode(422);

        $json_response = json_decode($this->_response->body(), true);
        $this->assertNotEmpty($json_response);
        $this->assertContains('errors', array_keys($json_response));

        $this->assertArrayHasKey('avatar', $json_response['errors']);
        $expected_error = [
            [
                'code' => 'MimeType',
                'message' => 'Invalid file type. Please upload images only (gif, png, jpg).'
            ]
        ];
        $this->assertEquals($expected_error, $json_response['errors']['avatar']);
    }

}
