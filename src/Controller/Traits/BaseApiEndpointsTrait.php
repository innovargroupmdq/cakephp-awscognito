<?php
namespace EvilCorp\AwsCognito\Controller\Traits;

use EvilCorp\AwsApiGateway\Error\UnprocessableEntityException;
use Cake\Filesystem\File;
use Mimey\MimeTypes;
use Cake\Utility\Hash;

trait BaseApiEndpointsTrait
{
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

        $this->ApiUsers->saveOrFail($api_user);

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

        $file_raw       = $this->request->input();
        $content_length = (int) Hash::get($this->request->getHeader('Content-Length'), '0', 0);

        $data = [
            'avatar_file_name' => $this->_RawFileToPHPFormat($file_raw, $content_length)
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

    /* protected methods */

    protected function _checkFileErrors($file_raw, $content_length)
    {
        $file_size = mb_strlen($file_raw, '8bit');

        $error = ($file_size !== $content_length)
            ? UPLOAD_ERR_PARTIAL
            : UPLOAD_ERR_OK;

        $error = ($file_size === 0)
            ? UPLOAD_ERR_NO_FILE
            : $error;

        $max_size_mb = min(
            ini_get('upload_max_filesize'),
            ini_get('post_max_size'),
            ini_get('memory_limit')
        );

        $max_size = $max_size_mb * 1024 * 1024;

        $error = ($max_size_mb > 0 && $file_size > $max_size)
            ? UPLOAD_ERR_INI_SIZE
            : $error;

        return $error;
    }

    protected function _RawFileToPHPFormat($file_raw, $content_length)
    {
        $error = $this->_checkFileErrors($file_raw, $content_length);

        $tmp_file = tempnam(sys_get_temp_dir(), 'avatar_');
        file_put_contents($tmp_file, $file_raw);

        $file_obj = new File($tmp_file);
        $mime_type = $file_obj->mime();

        $file_name = implode('.', [
            $file_obj->name(),
            (new MimeTypes())->getExtension($mime_type)
        ]);

        return [
            'name'     => $file_name,
            'type'     => $mime_type,
            'tmp_name' => $tmp_file,
            'error'    => $error,
            'size'     => $content_length
        ];
    }

}
