<?php
namespace EvilCorp\AwsCognito\Controller;

use EvilCorp\AwsCognito\Controller\AppController;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;
use Muffin\Footprint\Auth\FootprintAwareTrait;
use Cake\Core\Configure;

class ApiUsersController extends AppController
{

    use FootprintAwareTrait;

    public $paginate = [
        'limit' => 100,
        'order' => ['ApiUsers.aws_cognito_username' => 'asc'],
    ];

    public function index()
    {
        $this->set('api_users', $this->paginate('ApiUsers'));
        $this->set('_serialize', ['ApiUsers']);
    }

    public function view($id = null)
    {
        $api_user = $this->ApiUsers->getWithCognitoData($id, [
            'contain' => [
                'Creators',
                'Modifiers'
            ]
        ]);
        $this->set('api_user', $api_user);
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
            $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
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
            $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $api_user = $this->ApiUsers->get($id, [
            'contain' => []
        ]);

        if ($this->ApiUsers->delete($api_user)) {
            $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been deleted'));
        } else {
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be deleted'));
        }

        return $this->redirect(['action' => 'index']);
    }

    protected function _toggleActive($id, $value, $success, $error)
    {
        $this->request->allowMethod(['post']);

        $api_user = $this->ApiUsers->get($id);
        $api_user->active = $value;

        $result = $this->ApiUsers->save($api_user);

        if($result){
            $this->Flash->success($success);
        }else{
            $this->Flash->error($error);
        }

        return $this->redirect($this->request->referer());
    }

    public function activate($id = null)
    {
        return $this->_toggleActive($id, 1,
            __('The API User has been activated'),
            __('The API User could not be activated. Please, try again.')
        );
    }

    public function deactivate($id = null)
    {
        return $this->_toggleActive($id, 0,
            __('The API User has been deactivated'),
            __('The API User could not be deactivated. Please, try again.')
        );
    }

    public function resetPassword($id = null)
    {
        $this->request->allowMethod(['post']);
        $api_user = $this->ApiUsers->get($id);

        $cognito_user = $this->ApiUsers->getCognitoUser($api_user);

        if(!$cognito_user['Attributes']['email_verified']){
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The user email must be verified before resetting the password'));
        }elseif ($this->ApiUsers->resetCognitoPassword($api_user)) {
            $this->Flash->success(__d('EvilCorp/AwsCognito', 'The password has been reset'));
        } else {
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The password could not be reset. If the user has not yet changed their temporary password, they must do so before the password can be reset'));
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
            $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
            return $this->redirect(['action' => 'index']);
        }
        $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
    }

    public function import()
    {
        if($this->request->is('post')){

            $csv_data = $this->request->getData('csv_data', null);

            if(empty($csv_data)){
                //save rows
                $result = $this->ApiUsers->importMany($this->request->getData());
                if($result){
                    $this->Flash->success(__d('EvilCorp/AwsCognito', '{0} API Users imported successfully', count($result)));
                }else{
                    $this->Flash->error(__d('EvilCorp/AwsCognito', 'An error occurred while trying to import the API Users. Please try again.'));
                }
                return $this->redirect(['action' => 'index']);
            }

            //check for max rows
            $max_rows  = Configure::read('ApiUsers.import_max_rows');
            $data_rows = count(explode("\n", $csv_data));
            if($data_rows > $max_rows){
                $this->Flash->error(__d('EvilCorp/AwsCognito',
                    'The max amount of rows allowed is {0}. The data sent has {1} rows. Please review this and try again.',
                    $max_rows, $data_rows)
                );
                return;
            }

            //set max errors
            $max_errors = $this->request->getData('max_errors')
                ? Configure::read('ApiUsers.import_max_errors')
                : false;

            //validate data
            $rows = $this->ApiUsers->csvDataToAssociativeArray($csv_data);
            $api_users = $this->ApiUsers->validateMany($rows, $max_errors);

            $this->set([
                'api_users'             => $api_users,
                'rows_count'            => count($rows),
                'analyzed_rows_count'   => count($api_users),
                'stopped_at_max_errors' => ($max_errors && count($api_users) < count($rows)),
                'max_errors'            => $max_errors,
            ]);

            $this->render('import_validated');
        }
    }
}