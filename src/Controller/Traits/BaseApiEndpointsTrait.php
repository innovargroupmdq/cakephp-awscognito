<?php
namespace EvilCorp\AwsCognito\Controller\Traits;

use EvilCorp\AwsApiGateway\Error\UnprocessableEntityException;
use EvilCorp\AwsCognito\Controller\Traits\ApiUploadFilesTrait;
use Cake\ORM\Exception\PersistenceFailedException;

trait BaseApiEndpointsTrait
{

    use ApiUploadFilesTrait;

    /* override these methods for easy customization */

    protected function _viewUser($id)
    {
        return $this->ApiUsers->get($id, [
            'fields' => [
                'username' => 'aws_cognito_username',
                'email',
                'first_name',
                'last_name',
                'avatar' => 'avatar_url',
                'role'
            ]
        ]);
    }

    protected function _editableFields()
    {
        return [
            '*'          => false,
            'first_name' => true,
            'last_name'  => true,
        ];
    }

    /* exposed endpoints */

    public function profile()
    {
        $this->request->allowMethod('get');
        $api_user = $this->_viewUser($this->Auth->user('id'));
        $this->set('data', $api_user);
    }

    public function editProfile()
    {
        $this->request->allowMethod('patch');
        $user_id = $this->Auth->user('id');
        $data = $this->request->getData();

        $api_user = $this->ApiUsers->get($user_id);
        $api_user = $this->ApiUsers->patchEntity($api_user, $data, [
            'accessibleFields' => $this->_editableFields()
        ]);

        try {
            $this->ApiUsers->saveOrFail($api_user);
        } catch (PersistenceFailedException $e) {
            $entity = $e->getEntity();
            $entity_errors = $entity->getErrors();
            throw new UnprocessableEntityException([
                'message' => __d('api', 'Data Validation Failed'),
                'errors' => $entity->getErrors()
            ]);
        }

        $api_user = $this->_viewUser($user_id);
        $this->set('data', $api_user);
    }

    public function changeEmail()
    {
        $this->request->allowMethod('put');
        $user_id = $this->Auth->user('id');

        $api_user = $this->ApiUsers->get($user_id);
        $new_email = $this->request->getData('email');

        if (!$this->ApiUsers->changeEmail($api_user, $new_email, true)) {
            throw new UnprocessableEntityException([
                'message' => __d('api', 'Data Validation Failed'),
                'errors' => $api_user->getErrors()
            ]);
        }

        $api_user = $this->_viewUser($user_id);
        $this->set('data', $api_user);
    }

    public function uploadAvatar()
    {
        $this->request->allowMethod('put');

        $user_id = $this->Auth->user('id');
        $api_user = $this->ApiUsers->get($user_id);

        $data = [
            'avatar_file_name' => $this->_getFileFromRequest()
        ];

        $api_user = $this->ApiUsers->patchEntity($api_user, $data, [
            'accessibleFields' => [
                'avatar_file_name' => true,
            ]
        ]);

        if(!$this->ApiUsers->save($api_user)){
            throw new UnprocessableEntityException([
                'message' => __d('api', 'Data Validation Failed'),
                'errors' => [
                    'avatar' => $api_user->getError('avatar_file_name')
                ],
            ]);
        }

        $api_user = $this->_viewUser($user_id);

        $this->set('data', $api_user);
        $this->set('_serialize', ['data']);

    }
}
