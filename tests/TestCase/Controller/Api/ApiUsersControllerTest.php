<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller;

use EvilCorp\AwsCognito\Controller\Api\ApiUsersController;
use Cake\TestSuite\IntegrationTestCase;

use Cake\ORM\TableRegistry;
use Cake\Core\Configure;

class ApiUsersControllerTest extends IntegrationTestCase
{

    public $fixtures = [
        'plugin.EvilCorp/AwsCognito.api_users',
    ];

    protected function authorizeApiUser()
    {
        $api_id = 'test';

        Configure::write('AwsApiGateway.api_id', $api_id);

        $token = 'eyJraWQiOiJQdlB3RHRaeFp0SjFTSHJnZzA3Um92QzRnN1ZPcTFJQ2RyRWtWa1FhcFFFPSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiI5YTJiNDNlNC1lMDAyLTRiMWEtODliMy0xM2EzMGU1OWM5Y2UiLCJhdWQiOiI2aGdpZTFkaGtzbjN2c212ZGJsYXVsbGlkNSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJldmVudF9pZCI6Ijg4YmE5NjZiLTQ5NzktMTFlOC1iNzhlLWI1ZDY5NTk0MDZiYSIsInRva2VuX3VzZSI6ImlkIiwiYXV0aF90aW1lIjoxNTI0NzY0Njk2LCJpc3MiOiJodHRwczpcL1wvY29nbml0by1pZHAudXMtZWFzdC0xLmFtYXpvbmF3cy5jb21cL3VzLWVhc3QtMV9yam96MUhPYVIiLCJjb2duaXRvOnVzZXJuYW1lIjoidGVzdDEyMyIsImV4cCI6MTUyNDc2ODI5NiwiaWF0IjoxNTI0NzY0Njk3LCJlbWFpbCI6ImxvcmVuem9AZXZpbGNvcnAuY29tLmFyIn0.V6pXRtIsSJnUTuhTFcdwPvgTutpOqDDcbZHh9IHYtUUamA6B1hOsphR2BsQL9SGmMO0CyA9WfUEZCfsO7mnD9KfQYYigoXigbyS8bRUP_zS_CCHWTW2BaKQgV_ZHerZJO_9W_D6YcW4sMcPU2dweZkDA3hHvctN_turQhV-RokdbE7CdZQHIkY0kHt0vUSaU7gINNOn1Ovr_ZCmRvCjU93LH4fU1Erh0FP8DQC7BOxQtvftsXkF-jjmI_asmRyWNwAYP2OLDgRD9dRE7KxXGk5e5ppUt3AZfXBEjG61qjtQhQiXY-PS7dpCRji6STO8l34xpSi3sqqp9DPknVZhakw';

        $this->configRequest([
            'headers' => [
                'Accept'                   => 'application/json',
                'Content-Type'             => 'application/json',
                'X-Amzn-Apigateway-Api-Id' => $api_id,
                'Authorization'            => $token,
            ]
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

}
