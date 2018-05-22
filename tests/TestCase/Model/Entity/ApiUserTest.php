<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Model\Entity;

use EvilCorp\AwsCognito\Model\Entity\ApiUser;
use Cake\TestSuite\TestCase;

class ApiUserTest extends TestCase
{
	public function setUp()
	{
		parent::setUp();
		$this->ApiUser = new ApiUser();
	}

	public function tearDown()
	{
		unset($this->ApiUser);
		parent::tearDown();
	}

	public function testGetFullName()
	{
		$this->ApiUser->first_name = 'Jorge';
		$this->ApiUser->last_name = 'Perez';
		$this->assertEquals('Jorge Perez', $this->ApiUser->full_name);
	}


}