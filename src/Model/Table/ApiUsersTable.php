<?php
namespace EvilCorp\AwsCognito\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

use Cake\Core\Configure;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\Exception\AwsException;

use Cake\Event\Event;
use ArrayObject;
use Exception;
use EvilCorp\AwsCognito\Model\Entity\ApiUser;
use Cake\Datasource\EntityInterface;


class ApiUsersTable extends Table
{
    protected $CognitoClient;
    protected $UserPoolId;

    public function getRoles(){
        return  [
            'agent'     => __('Agent'),
            'dashboard' => __('Dashboard'),
        ];
    }

	public function initialize(array $config)
    {
    	parent::initialize($config);

        $this->setTable('api_users');
        $this->setDisplayField('full_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        if(!Configure::check('AWS.user_pool_id')){
            throw new Exception(__('the AWS User Pool ID has not been set.'));
        }

        $this->UserPoolId = Configure::read('AWS.user_pool_id');

        $this->CognitoClient = $this->createCognitoClient();
    }

    public function validationDefault(Validator $validator)
    {
        $validator
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('aws_cognito_username', 'create')
            ->notEmpty('username');

        $validator
            ->requirePresence('email', 'create')
            ->email('email')
            ->notEmpty('email');

        $validator
            ->requirePresence('role', 'create')
            ->inList('role', array_keys($this->getRoles()),
                sprintf(
                    __('Must be one of the following values: %s'),
                    implode(
                        ', ', array_keys( $this->getRoles())
                    )
                )
            )
            ->notEmpty('role');

        $validator
            ->allowEmpty('first_name');

        $validator
            ->allowEmpty('last_name');

        return $validator;
    }

    public function validationResendInvitationEmail(Validator $validator)
    {
        $validator = $this->validationDefault($validator);

        $validator->requirePresence('email');

        return $validator;
    }

    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->isUnique(['aws_cognito_username']), '_isUnique', [
            'errorField' => 'aws_cognito_username',
            'message' => __('Username already exists')
        ]);

        $rules->add($rules->isUnique(['email']), '_isUnique', [
            'errorField' => 'email',
            'message' => __('Email already exists')
        ]);

        return $rules;
    }

    public function resendInvitationEmail(ApiUser $entity)
    {
        if($entity->isNew()){
            throw new Exception(__('You must create the entity before trying to resend the invitation email'));
        }

        $entity = $this->createCognitoUser($entity, 'RESEND');

        return $this->save($entity);
    }


    public function beforeSave(Event $event, ApiUser $entity, ArrayObject $options)
    {
        if(!$this->isCognitoUser($entity)) return true;

        if($entity->isNew()){
            $entity = $this->createCognitoUser($entity);
        }else{

            //enable/disable user
            if($entity->active && $entity->dirty('active')){
                $this->enableCognitoUser($entity);
            }elseif(!$entity->active && $entity->dirty('active')){
                $this->disableCognitoUser($entity);
            }

            //change email
            if($entity->dirty('email')){
                //Note: changing the email unverifies it.
                $this->updateCognitoUserAttributes($entity);
            }
        }
    }

    public function beforeDelete(Event $event, ApiUser $entity, ArrayObject $options)
    {
        if(!$this->isCognitoUser($entity)) return true;

        $this->deleteCognitoUser($entity);
    }

    public function getCognitoUser(ApiUser $entity)
    {
        if(!$this->isCognitoUser($entity)){
            throw new Exception(__('The user is not a Cognito user.'));
        }

        $cognito_user = $this->CognitoClient->adminGetUser([
            'UserPoolId' => $this->UserPoolId,
            'Username'   => $entity->aws_cognito_username,
        ]);

        $cognito_user = $this->parseCognitoUser($cognito_user);

        if($entity->aws_cognito_id !== $cognito_user['Attributes']['sub']){
            throw new Exception(__('The returned Cognito user SUB does not match the client ID'));
        }

        return $cognito_user;
    }

    public function resetCognitoPassword(ApiUser $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__('The user does not have a Cognito Username.'));
        }

        try {
            $this->CognitoClient->adminResetUserPassword([
                'UserPoolId' => $this->UserPoolId,
                'Username'   => $entity->aws_cognito_username,
            ]);
        } catch (AwsException $e) {
            //NotAuthorizedException means the user's password cannot be reset.
            //Usually because the user hasn't logged in and changed the temp password yet.
            if($e->getAwsErrorCode() === 'NotAuthorizedException') return false;

            //InvalidParameterException means the user's email/phone is not verified,
            //so a verification message cannot be send.
            if($e->getAwsErrorCode() === 'InvalidParameterException') return false;

            throw $e;
        }

        return true;
    }

    protected function createCognitoClient()
    {
        if(!Configure::check('AWS.access_key_id')
        || !Configure::check('AWS.access_key_secret')){
            throw new Exception(__('the AWS credentials have not been set.'));
        }

        $access_key_id     = Configure::read('AWS.access_key_id');
        $access_key_secret = Configure::read('AWS.access_key_secret');

        $aws_credentials = new Credentials($access_key_id, $access_key_secret);

        $default_settings = [
            'credentials' => $aws_credentials,
            'version'     => '2016-04-18',
            'region'      => 'us-east-1',
            'debug'       => false,
        ];

        $settings = Configure::check('AWS.settings')
            ? array_merge($default_settings, Configure::read('AWS.settings'))
            : $default_settings;

        $s3_client = new CognitoIdentityProviderClient($settings);

        return $s3_client;

    }

    protected function parseCognitoUser(Result $cognito_user)
    {
        $cognito_user = $cognito_user->hasKey('User')
            ? $cognito_user->get('User')
            : $cognito_user->toArray();

        $attributes_key = isset($cognito_user['Attributes']) ? 'Attributes' : 'UserAttributes';

        $cognito_user['Attributes'] = array_combine(
            array_map(function($attr){return $attr['Name']; }, $cognito_user[$attributes_key]),
            array_map(function($attr){
                if($attr['Value'] === 'true') return true;
                if($attr['Value'] === 'false') return false;
                return $attr['Value'];
            }, $cognito_user[$attributes_key])
        );

        return $cognito_user;
    }

    protected function createCognitoUser(ApiUser $entity, $message_action = null)
    {
        //if the created user never logged in using the temp password,
        //the invitation must be re-sent to be used again.
        if(!empty($message_action) && !in_array($message_action, ['RESEND', 'SUPPRESS'])){
            throw new Exception('createCognitoUser parameter must be either "RESEND" or "SUPRESS" or null.');
        }

        $options = [
            'DesiredDeliveryMediums' => ['EMAIL'],
            'ForceAliasCreation'     => false,
            'UserAttributes' => [
                [
                'Name' => 'email',
                'Value' => $entity->email,
                ],
                [
                'Name' => 'email_verified',
                'Value' => 'true',
                ],
            ],
            'UserPoolId' => $this->UserPoolId,
            'Username'   => $entity->aws_cognito_username,
        ];

        if(!empty($message_action)){
            $options['MessageAction'] = $message_action;
        }

        try {
            //ref https://docs.aws.aws.com/cognito-user-identity-pools/latest/APIReference/API_AdminCreateUser.html
            $cognito_user = $this->parseCognitoUser($this->CognitoClient->adminCreateUser($options));
        } catch (AwsException $e) {
            //this exception is thrown when a user email or phone is already being
            //used by another user
            if($e->getAwsErrorCode() === 'AliasExistsException') return false;

            //this exception is thrown when the username already exists
            if($e->getAwsErrorCode() === 'UsernameExistsException') return false;

            throw $e;
        }

        $entity->aws_cognito_id = $cognito_user['Attributes']['sub'];
        $entity->aws_cognito_username = $cognito_user['Username'];

        if(!$entity->active){
            //if creating disabled, try to disabled it.
            try {
                $this->disableCognitoUser($entity);
            } catch (Exception $e) {
                //if for some reason it fails, prioritize consistency
                $entity->active = 1;
            }
        }

        return $entity;
    }

    protected function updateCognitoUserAttributes(ApiUser $entity)
    {
        //uploads all synced fields to make sure the cognito instance is up to date

        if(empty($entity->aws_cognito_username)){
            throw new Exception(__('The user does not have a Cognito Username.'));
        }

        $cognito_attributes_map = [
            //Cognito name => local value
            'email' => $entity->email,
            'email_verified' => 'true',
        ];

        $attributes = [];
        foreach ($cognito_attributes_map as $key => $value) {
            $attributes[] = [
                'Name' => $key,
                'Value' => (string)$value
            ];
        }

        $this->CognitoClient->adminUpdateUserAttributes([
            'UserAttributes' => $attributes,
            'UserPoolId'     => $this->UserPoolId,
            'Username'       => $entity->aws_cognito_username,
        ]);

        return true;
    }

    public function deleteCognitoUser(ApiUser $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__('The user does not have a Cognito Username.'));
        }

        try {
            $this->CognitoClient->adminDeleteUser([
                'UserPoolId' => $this->UserPoolId,
                'Username'   => $entity->aws_cognito_username,
            ]);
        } catch (AwsException $e) {
            //this exception is thrown when the user doesn't exist in cognito. probably already deleted!
            if($e->getAwsErrorCode() === 'UserNotFoundException') return true;

            throw $e;
        }

        return true;
    }

    protected function disableCognitoUser(ApiUser $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__('The user does not have a Cognito Username.'));
        }

        $this->CognitoClient->adminDisableUser([
            'UserPoolId' => $this->UserPoolId,
            'Username'   => $entity->aws_cognito_username,
        ]);

        return true;
    }

    protected function enableCognitoUser(ApiUser $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__('The user does not have a Cognito Username.'));
        }

        $this->CognitoClient->adminEnableUser([
            'UserPoolId' => $this->UserPoolId,
            'Username'   => $entity->aws_cognito_username,
        ]);

        return true;
    }

    protected function isCognitoUser($entity)
    {
        $is_new = true;

        if($entity instanceof EntityInterface){
            $is_new = $entity->isNew();
            $entity->hiddenProperties([]);
            $entity = $entity->toArray();
        }

        if(empty($entity['role'])) return false;

        if(!$is_new){
            if(empty($entity['aws_cognito_id'])) return false;
            if(empty($entity['aws_cognito_username'])) return false;
        }

        return true;
    }

}
