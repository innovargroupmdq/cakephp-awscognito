<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller\Traits;
use EvilCorp\AwsCognito\Test\TestCase\Controller\Traits\BaseTraitTest;
use EvilCorp\AwsCognito\Controller\Traits\AwsCognitoTrait;
use Cake\Datasource\Exception\RecordNotFoundException;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;

class AwsCognitoTraitTest extends BaseTraitTest
{
    public $viewVars;

    public function setUp()
    {
        $this->traitClassName = AwsCognitoTrait::class;
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

    public function testActivateSuccess()
    {
        $api_user = $this->ApiUsers->find()
            ->where(['active' => 0])
            ->first();
        $this->assertNotEmpty($api_user);
        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The API User has been activated');

        $this->Trait->activate($api_user->id);

        $this->assertSame(1, $this->ApiUsers->find()
            ->where(['ApiUsers.id' => $api_user->id, 'active' => 1])
            ->count());
    }

    public function testActivateFail()
    {
        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));

        $this->expectException(RecordNotFoundException::class);
        $this->Trait->activate(0);
    }

    public function testDeactivateSuccess()
    {
        $api_user = $this->ApiUsers->find()
            ->where(['active' => 1])
            ->first();
        $this->assertNotEmpty($api_user);
        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The API User has been deactivated');

        $this->Trait->deactivate($api_user->id);

        $this->assertSame(1, $this->ApiUsers->find()
            ->where(['ApiUsers.id' => $api_user->id, 'active' => 0])
            ->count());
    }

    public function testDeactivateFail()
    {
        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));

        $this->expectException(RecordNotFoundException::class);
        $this->Trait->deactivate(0);
    }

    public function testResetPasswordSuccess()
    {
        $api_user = $this->ApiUsers->getWithCognitoData(1);
        $api_user->aws_cognito_synced = true;
        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'resetCognitoPassword',
            'getWithCognitoData'
        ], [
            'alias' => 'ApiUsers'
        ]);
        $this->Trait->ApiUsers->expects($this->once())
            ->method('getWithCognitoData')
            ->with(1)
            ->will($this->returnValue($api_user));
        $this->Trait->ApiUsers->expects($this->once())
            ->method('resetCognitoPassword')
            ->with($api_user)
            ->will($this->returnValue(true));

        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The password has been reset');

        $this->Trait->resetPassword(1);
    }

    public function testResetPasswordFailNotSynced()
    {
        $api_user = $this->ApiUsers->getWithCognitoData(1);
        $api_user->aws_cognito_synced = false;
        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'resetCognitoPassword',
            'getWithCognitoData'
        ], [
            'alias' => 'ApiUsers'
        ]);
        $this->Trait->ApiUsers->expects($this->once())
            ->method('getWithCognitoData')
            ->with(1)
            ->will($this->returnValue($api_user));
        $this->Trait->ApiUsers->expects($this->never())
            ->method('resetCognitoPassword');

        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The API User is not synced with AWS Cognito');

        $this->Trait->resetPassword(1);
    }

    public function testResetPasswordFailEmailUnverified()
    {
        $api_user = $this->ApiUsers->getWithCognitoData(1);
        $api_user->aws_cognito_synced = true;
        $api_user->aws_cognito_attributes['email_verified'] = false;
        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'resetCognitoPassword',
            'getWithCognitoData'
        ], [
            'alias' => 'ApiUsers'
        ]);
        $this->Trait->ApiUsers->expects($this->once())
            ->method('getWithCognitoData')
            ->with(1)
            ->will($this->returnValue($api_user));
        $this->Trait->ApiUsers->expects($this->never())
            ->method('resetCognitoPassword');

        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The user email must be verified before resetting the password');

        $this->Trait->resetPassword(1);
    }

    public function testResetPasswordFailTempPasswordUnchanged()
    {
        $api_user = $this->ApiUsers->getWithCognitoData(1);
        $api_user->aws_cognito_synced = true;
        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'resetCognitoPassword',
            'getWithCognitoData'
        ], [
            'alias' => 'ApiUsers'
        ]);
        $this->Trait->ApiUsers->expects($this->once())
            ->method('getWithCognitoData')
            ->with(1)
            ->will($this->returnValue($api_user));
        $this->Trait->ApiUsers->expects($this->once())
            ->method('resetCognitoPassword')
            ->with($api_user)
            ->will($this->returnValue(false));

        $this->_mockRequestPost();
        $this->Trait->request->expects($this->any())
            ->method('allow')
            ->with(['post'])
            ->will($this->returnValue(true));
        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The password could not be reset. If the user has not yet changed their temporary password, they must do so before the password can be reset');

        $this->Trait->resetPassword(1);
    }

    public function testResendInvitationEmailGet()
    {
        $api_user = $this->ApiUsers->get(1);
        $this->_mockRequestGet();
        $this->Trait->resendInvitationEmail(1);
        $expected = [
            'api_user' => $api_user,
            '_serialize' => [
                'api_user',
            ]
        ];
        $this->assertEquals($expected, $this->viewVars);
    }

    public function testResendInvitationEmailPostSuccess()
    {
        $api_user = $this->ApiUsers->get(1);
        $new_email = 'new_email@newemail.com';

        $this->_mockRequestPost(['patch', 'post', 'put']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('email')
            ->will($this->returnValue($new_email));

        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('The Api User has been saved');

        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'resendInvitationEmail',
            'get'
        ], [
            'alias' => 'ApiUsers'
        ]);
        $this->Trait->ApiUsers->expects($this->once())
            ->method('get')
            ->with(1)
            ->will($this->returnValue($api_user));

        $this->Trait->ApiUsers->expects($this->once())
            ->method('resendInvitationEmail')
            ->with($api_user, $new_email)
            ->will($this->returnValue(true));

        $this->Trait->resendInvitationEmail(1);
    }

    public function testResendInvitationEmailPostFail()
    {
        $api_user = $this->ApiUsers->get(1);
        $new_email = 'new_email@newemail.com';

        $this->_mockRequestPost(['patch', 'post', 'put']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('email')
            ->will($this->returnValue($new_email));

        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The Api User could not be saved');

        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'resendInvitationEmail',
            'get'
        ], [
            'alias' => 'ApiUsers'
        ]);
        $this->Trait->ApiUsers->expects($this->once())
            ->method('get')
            ->with(1)
            ->will($this->returnValue($api_user));

        $this->Trait->ApiUsers->expects($this->once())
            ->method('resendInvitationEmail')
            ->with($api_user, $new_email)
            ->will($this->returnValue(false));

        $this->Trait->resendInvitationEmail(1);
    }

}
