<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Auth;

use Cake\TestSuite\TestCase;
use EvilCorp\AwsCognito\Auth\AwsCognitoJwtAuthenticate;

use Cake\Http\ServerRequest;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Exception;

class AwsCognitoJwtAuthenticateTest extends TestCase
{

	public $fixtures = [
		'plugin.EvilCorp/AwsCognito.api_users'
	];

	public function setUp()
	{
		Configure::write('AwsS3.local_only', true);
		$request  = new ServerRequest();
		$response = new Response();

		$this->ApiUsers = TableRegistry::get('EvilCorp/AwsCognito.ApiUsers');
		$this->Controller = new Controller($request, $response);
		$this->Authenticate = new AwsCognitoJwtAuthenticate(new ComponentRegistry($this->Controller), [
			'userModel' => 'ApiUsers'
		]);
	}

	public function tearDown()
	{
		unset($this->Authenticate, $this->Controller);
	}

	public function testAuthenticateFailNoAuthorizationHeader()
	{
		$result = $this->Authenticate->authenticate($this->Controller->request, $this->Controller->response);
		$this->assertFalse($result);
	}

	public function testAuthenticateFailBadToken()
	{
		$header = 'invalid header token';
		$this->Controller->setRequest($this->Controller->request->withHeader('Authorization', $header));
		$result = $this->Authenticate->authenticate($this->Controller->request, $this->Controller->response);
		$this->assertFalse($result);
	}

	public function testAuthenticateFailNoUserFound()
	{
		$header = 'eyJraWQiOiJ0S3ZoNGhRWUswR1RHbVFDZENReTE5bTkyNVpkM213QU5CY1ZmaktNTkVvPSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiI1ZTk1YmE4Yi01OTliLTQ3NDgtODA2ZC0wZWZkODI4OGU5ODYiLCJkZXZpY2Vfa2V5IjoidXMtZWFzdC0xXzIyM2MzNGU2LTFhZGEtNDRhMS1hYWM5LTg5YmJlNjU1MmYyNyIsInRva2VuX3VzZSI6ImFjY2VzcyIsInNjb3BlIjoiYXdzLmNvZ25pdG8uc2lnbmluLnVzZXIuYWRtaW4iLCJhdXRoX3RpbWUiOjE1MzU3MjUwMDIsImlzcyI6Imh0dHBzOlwvXC9jb2duaXRvLWlkcC51cy1lYXN0LTEuYW1hem9uYXdzLmNvbVwvdXMtZWFzdC0xX3Jqb3oxSE9hUiIsImV4cCI6MTUzNjc2OTc5NiwiaWF0IjoxNTM2NzY2MTk2LCJqdGkiOiI4NWFhYjliNS04OTkzLTQzNmMtOWQzNi1jMTA2YTg4MDcxNzEiLCJjbGllbnRfaWQiOiI2aGdpZTFkaGtzbjN2c212ZGJsYXVsbGlkNSIsInVzZXJuYW1lIjoiaWRhdGEifQ.aTfSIovtigQAC8BMJT9jWRbY0ZVm3JQGTzLIE5veZPwlxK1pNCScow5NOTvuLC__Nnk0BKLBSXpNEN-WlZO6C7pya86WcbR3nCOhxaNcrsq_6SQUNNv2wFZCYA6tLuqxsFb6-8fij_eOo_eqsJsFkqojoTVmFaLkQD0ikCmEe7x86EncLPs_0b4fToI0P9_5M_3ayC33KPlSyPtilLi8Upq21QqbAxTRu5E1WyC1P6cjl4Iq67HwGPluD1t0X_zFvQlhndIm7QvhyC8Qk6nLXklglI-U_t1iYeI8celWuKeR03NSLtAqDKxbD7CADW50vJ1hTkKxzCB4JibYyTN9LA';
		$this->Controller->setRequest($this->Controller->request->withHeader('Authorization', $header));
		$result = $this->Authenticate->authenticate($this->Controller->request, $this->Controller->response);
		$this->assertFalse($result);
	}

	public function testAuthenticateSuccess()
	{
		$user = $this->ApiUsers->find()
			->where(['aws_cognito_username' => 'test123'])
			->first();

		$header = 'eyJraWQiOiJQdlB3RHRaeFp0SjFTSHJnZzA3Um92QzRnN1ZPcTFJQ2RyRWtWa1FhcFFFPSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiI5YTJiNDNlNC1lMDAyLTRiMWEtODliMy0xM2EzMGU1OWM5Y2UiLCJhdWQiOiI2aGdpZTFkaGtzbjN2c212ZGJsYXVsbGlkNSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJldmVudF9pZCI6Ijg4YmE5NjZiLTQ5NzktMTFlOC1iNzhlLWI1ZDY5NTk0MDZiYSIsInRva2VuX3VzZSI6ImlkIiwiYXV0aF90aW1lIjoxNTI0NzY0Njk2LCJpc3MiOiJodHRwczpcL1wvY29nbml0by1pZHAudXMtZWFzdC0xLmFtYXpvbmF3cy5jb21cL3VzLWVhc3QtMV9yam96MUhPYVIiLCJjb2duaXRvOnVzZXJuYW1lIjoidGVzdDEyMyIsImV4cCI6MTUyNDc2ODI5NiwiaWF0IjoxNTI0NzY0Njk3LCJlbWFpbCI6ImxvcmVuem9AZXZpbGNvcnAuY29tLmFyIn0.V6pXRtIsSJnUTuhTFcdwPvgTutpOqDDcbZHh9IHYtUUamA6B1hOsphR2BsQL9SGmMO0CyA9WfUEZCfsO7mnD9KfQYYigoXigbyS8bRUP_zS_CCHWTW2BaKQgV_ZHerZJO_9W_D6YcW4sMcPU2dweZkDA3hHvctN_turQhV-RokdbE7CdZQHIkY0kHt0vUSaU7gINNOn1Ovr_ZCmRvCjU93LH4fU1Erh0FP8DQC7BOxQtvftsXkF-jjmI_asmRyWNwAYP2OLDgRD9dRE7KxXGk5e5ppUt3AZfXBEjG61qjtQhQiXY-PS7dpCRji6STO8l34xpSi3sqqp9DPknVZhakw';
		$this->Controller->setRequest($this->Controller->request->withHeader('Authorization', $header));
		$logged_user = $this->Authenticate->authenticate($this->Controller->request, $this->Controller->response);
		$this->assertNotFalse($logged_user);
		$this->assertEquals($user->aws_cognito_username, $logged_user['aws_cognito_username']);
		$this->assertEquals($user->aws_cognito_id, $logged_user['aws_cognito_id']);
	}

	public function testUnauthenticated()
	{
		$this->expectException(UnauthorizedException::class);
		$this->Authenticate->unauthenticated($this->Controller->request, $this->Controller->response);
	}


}