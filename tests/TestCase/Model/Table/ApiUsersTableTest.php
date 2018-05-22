<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Model\Table;

use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;

use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Cake\Core\Plugin;

class ApiUsersTableTest extends TestCase
{

	public $fixtures = [
		'plugin.EvilCorp/AwsCognito.api_users'
	];

	public function setUp()
	{
		parent::setUp();
		$this->ApiUsers = TableRegistry::get('EvilCorp/AwsCognito.ApiUsers');
		Plugin::routes('EvilCorp/AwsCognito');
	}

	public function tearDown()
	{
		unset($this->ApiUsers);
		parent::tearDown();
	}


	public function testBeforeSaveNewCreatedInCognito()
	{
		$this->markTestIncomplete();
	}

	public function testBeforeSaveExistingEdited()
	{
		$this->markTestIncomplete();
	}

	public function testBeforeSaveExistingEnabled()
	{
		$this->markTestIncomplete();
	}

	public function testBeforeSaveExistingDisabled()
	{
		$this->markTestIncomplete();
	}

	public function testBeforeDeleteDeletedInCognito()
	{
		$this->markTestIncomplete();
	}

	public function testGetRolesCheckConfiguration()
	{
		$this->markTestIncomplete();
	}

	public function testGetRolesValidConfiguration()
	{
		$this->markTestIncomplete();
	}

	public function testGetRolesSuccess()
	{
		$this->markTestIncomplete();
	}

	public function testResendInvitationEmailSucess()
	{
		$this->markTestIncomplete();
	}

	public function testResendInvitationEmailFailure()
	{
		$this->markTestIncomplete();
	}

	public function testGetCognitoUserSuccess()
	{
		$this->markTestIncomplete();
	}

	public function testGetCognitoUserFailureNotInCognito()
	{
		$this->markTestIncomplete();
	}

	public function testGetCognitoUserFailureInvalidUser()
	{
		$this->markTestIncomplete();
	}

	public function testResetCognitoPasswordSuccess()
	{
		$this->markTestIncomplete();
	}

	public function testResetCognitoPasswordFailureUserNeverLoggedIn()
	{
		$this->markTestIncomplete();
	}

	public function testResetCognitoPasswordFailureEmailNotVerified()
	{
		$this->markTestIncomplete();
	}

	public function testDeleteCognitoUserSuccessDeleted()
	{
		$this->markTestIncomplete();
	}

	public function testDeleteCognitoUserSuccessAlreadyDeleted()
	{
		$this->markTestIncomplete();
	}

	public function testDeleteCognitoUserFailureNoCognitoUsername()
	{
		$this->markTestIncomplete();
	}

}