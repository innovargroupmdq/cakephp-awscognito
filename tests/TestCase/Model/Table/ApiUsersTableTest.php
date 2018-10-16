<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Model\Table;

use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Result;

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
		Plugin::routes('EvilCorp/AwsCognito');
	}

	public function tearDown()
	{
		TableRegistry::clear();
		parent::tearDown();
	}

}