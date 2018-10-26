<?php
namespace EvilCorp\AwsCognito\Controller\Traits;

trait BaseCrudTrait
{

	public function index()
    {
        $query = $this->ApiUsers
            ->find('search', ['search' => $this->request->getQueryParams()]);

        $api_users = $this->paginate($query);

        $this->set('isSearch', $this->ApiUsers->isSearch());
        $this->set('api_users', $api_users);
        $this->set('_serialize', ['api_users']);
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
        $this->set('_serialize', ['api_users']);
    }

    public function add()
    {
        $api_user = $this->ApiUsers->newEntity();
        $roles    = $this->ApiUsers->getRoles();

        if ($this->request->is('post')) {
            $api_user = $this->ApiUsers->patchEntity($api_user, $this->request->getData(), [
                'accessibleFields' => [
                    'role'                 => true,
                    'aws_cognito_username' => true,
                    'email'                => true,
                ]
            ]);

            if ($this->ApiUsers->save($api_user)) {
                $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
        }

        $this->set('api_user', $api_user);
        $this->set('roles', $roles);
        $this->set('_serialize', ['api_user', 'roles']);
    }


   public function edit($id = null)
    {
        $api_user = $this->ApiUsers->get($id);
        $roles = $this->ApiUsers->getRoles();

        if ($this->request->is(['patch', 'post', 'put'])) {
            $api_user = $this->ApiUsers->patchEntity($api_user, $this->request->getData(), [
                'accessibleFields' => [
                    'aws_cognito_username' => false,
                    'aws_cognito_id'       => false,
                    'email'                => false
                ]
            ]);
            if ($this->ApiUsers->save($api_user)) {
                $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
        }

        $this->set('api_user', $api_user);
        $this->set('roles', $roles);
        $this->set('_serialize', ['api_user', 'roles']);
    }

    public function changeEmail($id = null)
    {
        $api_user = $this->ApiUsers->get($id);
        $roles    = $this->ApiUsers->getRoles();

        if ($this->request->is(['patch', 'post', 'put'])) {
            $new_email = $this->request->getData('email');
            $require_verification = $this->request->getData('require_verification');

            if ($this->ApiUsers->changeEmail($api_user, $new_email, $require_verification)) {
                $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
        }

        $this->set('api_user', $api_user);
        $this->set('roles', $roles);
        $this->set('_serialize', ['api_user', 'roles']);
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $api_user = $this->ApiUsers->get($id);

        if ($this->ApiUsers->delete($api_user)) {
            $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been deleted'));
        } else {
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be deleted'));
        }

        return $this->redirect(['action' => 'index']);
    }
}