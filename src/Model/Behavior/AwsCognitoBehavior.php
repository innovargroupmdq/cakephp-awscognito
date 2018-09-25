<?php
namespace EvilCorp\AwsCognito\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Validation\Validator;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\Exception\AwsException;

use Cake\Event\Event;
use ArrayObject;
use Exception;

use Cake\Datasource\EntityInterface;

class AwsCognitoBehavior extends Behavior
{

	protected $_defaultConfig = [
		'AccessKeys' => [
			'id' => false,
			'secret' => false,
		],
		'UserPool' => [
			'id' => false,
		],
		'IdentityProviderClient' => [
			'settings' => [], //https://docs.aws.amazon.com/sdkforruby/api/Aws/CognitoIdentityProvider/Client.html#initialize-instance_method
		]
    ];

	protected $CognitoClient;

	public function initialize(array $config)
	{
		if(!$this->getConfig('UserPool.id')){
            throw new Exception(__d('EvilCorp/AwsCognito', 'the AWS User Pool ID has not been set.'));
        }

        $this->CognitoClient = $this->createCognitoClient();
	}

	/* Validation */

	public function validationResendInvitationEmail(Validator $validator)
    {
        $validator = $this->validationDefault($validator);

        $validator->requirePresence('email');

        return $validator;
    }

	/* Lifecycle Callbacks */

    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        if($entity->isNew()){
            /*
            better to have the cognito creation callback be beforeSave instead of afterSave,
            so that we only create users once we're sure they're in the cognito user pool.
            Best solution for most cases.
            */
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
                $this->updateCognitoUserAttributes($entity);
            }
        }
    }

    public function beforeDelete(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        /*
        NOTE: For practical purposes it is still better to have the cognito creation callback be beforeSave
        instead of afterSave, so that we only create users once we're sure they're in the cognito user pool.
        */
        $this->deleteCognitoUser($entity);
    }

    /* Public Methods */

    public function resendInvitationEmail(EntityInterface $entity)
    {
        //resends the invitation email in case it expired or the email address was incorrect.
        //Updates the email address if changed.

        if($entity->isNew()){
            throw new Exception(__d('EvilCorp/AwsCognito', 'You must create the entity before trying to resend the invitation email'));
        }

        $entity = $this->createCognitoUser($entity, 'RESEND');

        return $this->save($entity);
    }

    public function getCognitoUser(EntityInterface $entity)
    {
        //returns the cognito data for a given local user.

        $entity->hiddenProperties([]);

        if(empty($entity->aws_cognito_username) || empty($entity->aws_cognito_id)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user is not a Cognito user.'));
        }

        $cognito_user = $this->CognitoClient->adminGetUser([
            'UserPoolId' => $this->getConfig('UserPool.id'),
            'Username'   => $entity->aws_cognito_username,
        ]);

        $cognito_user = $this->processCognitoUser($cognito_user);

        if($entity->aws_cognito_id !== $cognito_user['Attributes']['sub']){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The returned Cognito user SUB does not match the client ID'));
        }

        return $cognito_user;
    }

    public function resetCognitoPassword(EntityInterface $entity)
    {
        //resets the user password.
        //Cannot be reset if the user hasn't login for the first time yet, or if the email/phone is not verified to send the verification message.

        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        }

        try {
            $this->CognitoClient->adminResetUserPassword([
                'UserPoolId' => $this->getConfig('UserPool.id'),
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

    public function deleteCognitoUser(EntityInterface $entity)
    {
        /*
        used in beforeDelete callback to ensure the user is also deleted in the user pool.
        NOTE: this method is public because it is possible to create many users in a transaction and
        have the transaction fail, in which case you'd need to delete all the new users from cognito manually.
        */

        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        }

        try {
            $this->CognitoClient->adminDeleteUser([
                'UserPoolId' => $this->getConfig('UserPool.id'),
                'Username'   => $entity->aws_cognito_username,
            ]);
        } catch (AwsException $e) {
            //this exception is thrown when the user doesn't exist in cognito. probably already deleted!
            if($e->getAwsErrorCode() === 'UserNotFoundException') return true;

            throw $e;
        }

        return true;
    }

    /* Protected Methods */

    protected function createCognitoClient()
    {
        if(!$this->getConfig('AccessKeys.id', false)
        || !$this->getConfig('AccessKeys.secret', false)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'the AWS credentials have not been set.'));
        }

        $access_key_id     = $this->getConfig('AccessKeys.id');
        $access_key_secret = $this->getConfig('AccessKeys.secret');

        $aws_credentials = new Credentials($access_key_id, $access_key_secret);

        $default_settings = [
            'credentials' => $aws_credentials,
            'version'     => '2016-04-18',
            'region'      => 'us-east-1',
            'debug'       => false,
        ];

        $settings = $this->getConfig('IdentityProviderClient.settings', false)
            ? array_merge($default_settings, $this->getConfig('IdentityProviderClient.settings'))
            : $default_settings;

        $s3_client = new CognitoIdentityProviderClient($settings);

        return $s3_client;

    }

    protected function processCognitoUser(Result $cognito_user)
    {
        //processes the cognito user returned by the sdk into a more human readable array

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

    protected function createCognitoUser(EntityInterface $entity, $message_action = null)
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
            'UserPoolId' => $this->getConfig('UserPool.id'),
            'Username'   => $entity->aws_cognito_username,
        ];

        if(!empty($message_action)){
            $options['MessageAction'] = $message_action;
        }

        try {
            //ref https://docs.aws.aws.com/cognito-user-identity-pools/latest/APIReference/API_AdminCreateUser.html
            $cognito_user = $this->processCognitoUser($this->CognitoClient->adminCreateUser($options));
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

    protected function updateCognitoUserAttributes(EntityInterface $entity)
    {
        //uploads all synced fields to make sure the cognito instance is up to date

        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
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
            'UserPoolId'     => $this->getConfig('UserPool.id'),
            'Username'       => $entity->aws_cognito_username,
        ]);

        return true;
    }

    protected function disableCognitoUser(EntityInterface $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        }

        $this->CognitoClient->adminDisableUser([
            'UserPoolId' => $this->getConfig('UserPool.id'),
            'Username'   => $entity->aws_cognito_username,
        ]);

        return true;
    }

    protected function enableCognitoUser(EntityInterface $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        }

        $this->CognitoClient->adminEnableUser([
            'UserPoolId' => $this->getConfig('UserPool.id'),
            'Username'   => $entity->aws_cognito_username,
        ]);

        return true;
    }

}