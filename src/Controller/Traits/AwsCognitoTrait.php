<?php
namespace EvilCorp\AwsCognito\Controller\Traits;

trait AwsCognitoTrait
{

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
        $api_user = $this->ApiUsers->getWithCognitoData($id);
        if(!$api_user->aws_cognito_synced){
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The API User is not synced with AWS Cognito'));
        }elseif(!$api_user->aws_cognito_attributes['email_verified']){
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
        $api_user = $this->ApiUsers->get($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $email = $this->request->getData('email');
            if ($this->ApiUsers->resendInvitationEmail($api_user, $email)) {
                $this->Flash->success(__d('EvilCorp/AwsCognito', 'The Api User has been saved'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'The Api User could not be saved'));
        }

        $this->set('api_user', $api_user);
        $this->set('_serialize', ['api_user']);
    }

}