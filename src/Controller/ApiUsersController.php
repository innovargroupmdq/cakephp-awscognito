<?php
namespace EvilCorp\AwsCognito\Controller;

use EvilCorp\AwsCognito\Controller\AppController;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;

class ApiUsersController extends AppController
{

    public $paginate = [
        'limit' => 100,
        'order' => ['ApiUsers.aws_cognito_username' => 'asc'],
    ];

    public function index()
    {
        //$this->paginate['contain'] = ['PointsOfSale'];

        $this->set('api_users', $this->paginate('ApiUsers'));
        $this->set('_serialize', ['ApiUsers']);
    }

    public function view($id = null)
    {
        $api_user = $this->ApiUsers->get($id, [
            'contain' => ['PointsOfSale']
        ]);
        $this->set('api_user', $api_user);
        $this->set('cognito_user', $this->ApiUsers->getCognitoUser($api_user));
        $this->set('tableAlias', 'ApiUsers');
        $this->set('_serialize', ['ApiUsers']);
    }

    public function add()
    {
        $api_user = $this->ApiUsers->newEntity();
        $roles    = $this->ApiUsers->getRoles();

        $this->set(compact('api_user', 'roles'));
        $this->set('_serialize', ['api_user', 'roles']);

        if (!$this->request->is('post')) {
            return;
        }

        $api_user = $this->ApiUsers->patchEntity($api_user, $this->request->getData(), [
            'accessibleFields' => [
                'role' => true,
                'aws_cognito_username' => true,
                'email' => true,
            ]
        ]);

        if ($this->ApiUsers->save($api_user)) {
            $this->Flash->success(__('The Api User has been saved'));
            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__('The Api User could not be saved'));
    }


   public function edit($id = null)
    {
        $api_user = $this->ApiUsers->get($id, [
            'contain' => []
        ]);
        $roles    = $this->ApiUsers->getRoles();
        $this->set(compact('api_user', 'roles'));
        $this->set('_serialize', ['api_user', 'roles']);

        if (!$this->request->is(['patch', 'post', 'put'])) {
            return;
        }
        $api_user = $this->ApiUsers->patchEntity($api_user, $this->request->getData(), [
            'accessibleFields' => [
                'email' => true,
            ]
        ]);
        if ($this->ApiUsers->save($api_user)) {
            $this->Flash->success(__('The Api User has been saved'));
            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__('The Api User could not be saved'));
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $api_user = $this->ApiUsers->get($id, [
            'contain' => []
        ]);

        if ($this->ApiUsers->delete($api_user)) {
            $this->Flash->success(__('The Api User has been deleted'));
        } else {
            $this->Flash->error(__('The Api User could not be deleted'));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function resetPassword($id = null)
    {
        $this->request->allowMethod(['post']);
        $api_user = $this->ApiUsers->get($id);

        $cognito_user = $this->ApiUsers->getCognitoUser($api_user);

        if(!$cognito_user['Attributes']['email_verified']){
            $this->Flash->error(__('The user email must be verified before resetting the password'));
        }elseif ($this->ApiUsers->resetCognitoPassword($api_user)) {
            $this->Flash->success(__('The password has been reset'));
        } else {
            $this->Flash->error(__('The password could not be reset. If the user has not yet changed their temporary password, they must do so before the password can be reset'));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function resendInvitationEmail($id = null)
    {
        $api_user = $this->ApiUsers->get($id, [
            'contain' => []
        ]);
        $this->set(compact('api_user'));
        $this->set('_serialize', ['api_user']);

        if (!$this->request->is(['patch', 'post', 'put'])) {
            return;
        }

        $resend_invitation_fields = [
            'email' => $this->request->getData('email'),
        ];

        $api_user = $this->ApiUsers->patchEntity($api_user, $resend_invitation_fields, [
            'validate' => 'resendInvitationEmail'
        ]);

        if ($this->ApiUsers->resendInvitationEmail($api_user)) {
            $this->Flash->success(__('The Api User has been saved'));
            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__('The Api User could not be saved'));
    }
}