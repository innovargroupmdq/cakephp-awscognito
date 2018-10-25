<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller\Traits;
use EvilCorp\AwsCognito\Test\TestCase\Controller\Traits\BaseTraitTest;
use EvilCorp\AwsCognito\Controller\Traits\BaseCrudTrait;
use Cake\Datasource\Exception\RecordNotFoundException;

class BaseCrudTraitTest extends BaseTraitTest
{
    public $viewVars;

    public function setUp()
    {
        $this->traitClassName = BaseCrudTrait::class;
        $this->traitMockMethods = ['dispatchEvent', 'isStopped', 'redirect', 'set', 'paginate'];
        parent::setUp();
        $viewVarsContainer = $this;
        $this->Trait->expects($this->any())
            ->method('set')
            ->will($this->returnCallback(function ($param1, $param2 = null) use ($viewVarsContainer) {
                $viewVarsContainer->viewVars[$param1] = $param2;
            }));
    }

    public function tearDown()
    {
        $this->viewVars = null;
        parent::tearDown();
    }

    public function testIndex()
    {
        $this->_mockRequestGet();
        $this->Trait->request->expects($this->any())
                ->method('getQueryParams')
                ->will($this->returnValue([]));

        $query = $this->ApiUsers
            ->find('search', ['search' => []]);

        $this->Trait->expects($this->once())
            ->method('paginate')
            ->with($query)
            ->will($this->returnValue([]));
        $this->Trait->index();
        $expected = [
            'isSearch' => false,
            'api_users' => [],
            '_serialize' => [
                'api_users'
            ]
        ];
        $this->assertSame($expected, $this->viewVars);
    }

    public function testIndexSearch()
    {
        $this->_mockRequestGet();
        $this->Trait->request->expects($this->any())
                ->method('getQueryParams')
                ->will($this->returnValue(['q' => 'Pepito']));

        $query = $this->ApiUsers
            ->find('search', ['search' => ['q' => 'Pepito']]);

        $this->Trait->expects($this->once())
            ->method('paginate')
            ->with($query)
            ->will($this->returnValue([]));
        $this->Trait->index();
        $expected = [
            'isSearch' => true,
            'api_users' => [],
            '_serialize' => [
                'api_users'
            ]
        ];
        $this->assertSame($expected, $this->viewVars);
    }

    public function testView()
    {
        $api_user = $this->ApiUsers->getWithCognitoData(1, [
            'contain' => [
                'Modifiers',
                'Creators'
            ]
        ]);
        $this->Trait->view(1);
        $expected = [
            'api_user' => $api_user,
            '_serialize' => [
                'api_users'
            ]
        ];

        $this->assertEquals($expected, $this->viewVars);
    }

    public function testAddGet()
    {
        $this->_mockRequestGet();
        $this->Trait->add();
        $expected = [
            'api_user' => $this->ApiUsers->newEntity(),
            'roles' => $this->ApiUsers->getRoles(),
            '_serialize' => [
                'api_user',
                'roles'
            ]
        ];
        $this->assertEquals($expected, $this->viewVars);
    }

    public function testAddPostSuccess()
    {
        $this->_mockRequestPost();
        $this->_mockFlash();
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with()
            ->will($this->returnValue([
                'aws_cognito_username' => 'test_user_add',
                'email'                => 'testuseradd@test.com',
                'first_name'           => 'test',
                'last_name'            => 'user',
                'role'                 => array_keys($this->ApiUsers->getRoles())[0]
            ]));
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The Api User has been saved');

        $this->Trait->add();

        $this->assertSame(1, $this->ApiUsers->find()
            ->where(['aws_cognito_username' => 'test_user_add'])
            ->count()
        );
    }

    public function testAddPostFail()
    {
        $this->_mockRequestPost();
        $this->_mockFlash();
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with()
            ->will($this->returnValue([
                'aws_cognito_username' => 'test_user_add',
                'email'                => 'lorenzo@evilcorp.com.ar', //invalid email
                'first_name'           => 'test',
                'last_name'            => 'user',
                'role'                 => array_keys($this->ApiUsers->getRoles())[0]
            ]));

        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The Api User could not be saved');

        $this->Trait->add();

        $this->assertSame(0, $this->ApiUsers->find()
            ->where(['aws_cognito_username' => 'test_user_add'])
            ->count()
        );
    }

    public function testEditGet()
    {
        $api_user = $this->ApiUsers->get(1);
        $this->_mockRequestGet();
        $this->Trait->edit(1);
        $expected = [
            'api_user' => $api_user,
            'roles' => $this->ApiUsers->getRoles(),
            '_serialize' => [
                'api_user',
                'roles'
            ]
        ];
        $this->assertEquals($expected, $this->viewVars);
    }

    public function testEditPostSuccess()
    {
        $this->_mockRequestPost(['patch', 'post', 'put']);
        $this->_mockFlash();
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with()
            ->will($this->returnValue([
                'first_name'           => 'edited',
                'last_name'            => 'edited',
            ]));
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The Api User has been saved');

        $this->Trait->edit(1);

        $this->assertSame(1, $this->ApiUsers->find()
            ->where(['id' => 1, 'first_name' => 'edited', 'last_name' => 'edited'])
            ->count()
        );
    }

    public function testEditFail()
    {
        $this->_mockRequestPost(['patch', 'post', 'put']);
        $this->_mockFlash();
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with()
            ->will($this->returnValue([
                'first_name'           => '',
                'last_name'            => '',
            ]));
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The Api User could not be saved');

        $this->Trait->edit(1);

        $this->assertSame(0, $this->ApiUsers->find()
            ->where(['id' => 1, 'first_name' => '', 'last_name' => ''])
            ->count()
        );
    }

    public function testDeleteSuccess()
    {
        $this->assertNotEmpty($this->ApiUsers->get(1));
        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post', 'delete'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The Api User has been deleted');

        $this->Trait->delete(1);
        $this->expectException(RecordNotFoundException::class);
        $this->ApiUsers->get(1);
    }

    public function testDeleteFail()
    {
        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post', 'delete'])
            ->will($this->returnValue(true));

        $this->expectException(RecordNotFoundException::class);
        $this->Trait->delete(0);
    }

}
