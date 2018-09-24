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

use Cake\Utility\Hash;


class ApiUsersTable extends Table
{
    protected $CognitoClient;
    protected $UserPoolId;

	public function initialize(array $config)
    {
    	parent::initialize($config);

        $this->setTable('api_users');
        $this->setDisplayField('full_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        if(!Configure::check('AwsCognito.UserPool.id')){
            throw new Exception(__d('EvilCorp/AwsCognito', 'the AWS User Pool ID has not been set.'));
        }

        $this->UserPoolId = Configure::read('AwsCognito.UserPool.id');

        $this->CognitoClient = $this->createCognitoClient();
    }

    /* Validation Methods */

    public function validationDefault(Validator $validator)
    {
        $validator
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('aws_cognito_username', 'create')
            ->notBlank('aws_cognito_username', __d('cake', 'This field cannot be left empty'));

        $validator
            ->requirePresence('email', 'create')
            ->add('email', [
                'email' => [
                    'rule' => 'email',
                    'last' => true,
                    'message' => __d('EvilCorp/AwsCognito', 'This field must be a valid email address')
                ]
            ])
            ->notBlank('email', __d('cake', 'This field cannot be left empty'));

        $validator
            ->requirePresence('first_name', 'create')
            ->notBlank('first_name', __d('cake', 'This field cannot be left empty'));

        $validator
            ->requirePresence('last_name', 'create')
            ->notBlank('last_name', __d('cake', 'This field cannot be left empty'));

        $validator
            ->requirePresence('role', 'create')
            ->inList('role', array_keys($this->getRoles()),
                sprintf(
                    __d('EvilCorp/AwsCognito', 'Must be one of the following values: %s'),
                    implode(
                        ', ', array_keys( $this->getRoles())
                    )
                )
            )
            ->notEmpty('role');

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
            'message' => __d('EvilCorp/AwsCognito', 'Username already exists')
        ]);

        $rules->add($rules->isUnique(['email']), '_isUnique', [
            'errorField' => 'email',
            'message' => __d('EvilCorp/AwsCognito', 'Email already exists')
        ]);

        return $rules;
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

    public function beforeDelete(Event $event, ApiUser $entity, ArrayObject $options)
    {
        /*
        NOTE: For practical purposes it is still better to have the cognito creation callback be beforeSave
        instead of afterSave, so that we only create users once we're sure they're in the cognito user pool.
        */
        $this->deleteCognitoUser($entity);
    }

    /* Public Methods */

    public function getRoles(){
        //returns the array of configured roles, throws exception if not configured
        if(!Configure::check('ApiUsers.roles')){
            throw new Exception(__d('EvilCorp/AwsCognito', 'ApiUsers.roles setting is invalid'));
        }

        $roles = Configure::read('ApiUsers.roles');

        foreach ($roles as $role => $name) {
            if(is_numeric($role)) throw new Exception(__d('EvilCorp/AwsCognito', 'The ApiUsers.roles array should be entirely associative'));
        }

        return $roles;
    }

    public function resendInvitationEmail(ApiUser $entity)
    {
        //resends the invitation email in case it expired or the email address was incorrect.
        //Updates the email address if changed.

        if($entity->isNew()){
            throw new Exception(__d('EvilCorp/AwsCognito', 'You must create the entity before trying to resend the invitation email'));
        }

        $entity = $this->createCognitoUser($entity, 'RESEND');

        return $this->save($entity);
    }

    public function getCognitoUser(ApiUser $entity)
    {
        //returns the cognito data for a given local user.

        $entity->hiddenProperties([]);

        if(empty($entity->aws_cognito_username) || empty($entity->aws_cognito_id)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user is not a Cognito user.'));
        }

        $cognito_user = $this->CognitoClient->adminGetUser([
            'UserPoolId' => $this->UserPoolId,
            'Username'   => $entity->aws_cognito_username,
        ]);

        $cognito_user = $this->processCognitoUser($cognito_user);

        if($entity->aws_cognito_id !== $cognito_user['Attributes']['sub']){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The returned Cognito user SUB does not match the client ID'));
        }

        return $cognito_user;
    }

    public function resetCognitoPassword(ApiUser $entity)
    {
        //resets the user password.
        //Cannot be reset if the user hasn't login for the first time yet, or if the email/phone is not verified to send the verification message.

        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
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

    public function deleteCognitoUser(ApiUser $entity)
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

    /* Protected Methods */

    protected function createCognitoClient()
    {
        if(!Configure::check('AwsCognito.AccessKeys.id')
        || !Configure::check('AwsCognito.AccessKeys.secret')){
            throw new Exception(__d('EvilCorp/AwsCognito', 'the AWS credentials have not been set.'));
        }

        $access_key_id     = Configure::read('AwsCognito.AccessKeys.id');
        $access_key_secret = Configure::read('AwsCognito.AccessKeys.secret');

        $aws_credentials = new Credentials($access_key_id, $access_key_secret);

        $default_settings = [
            'credentials' => $aws_credentials,
            'version'     => '2016-04-18',
            'region'      => 'us-east-1',
            'debug'       => false,
        ];

        $settings = Configure::check('AwsCognito.IdentityProviderClient.settings')
            ? array_merge($default_settings, Configure::read('AwsCognito.IdentityProviderClient.settings'))
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

    protected function updateCognitoUserAttributes(ApiUser $entity)
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
            'UserPoolId'     => $this->UserPoolId,
            'Username'       => $entity->aws_cognito_username,
        ]);

        return true;
    }

    protected function disableCognitoUser(ApiUser $entity)
    {
        if(empty($entity->aws_cognito_username)){
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
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
            throw new Exception(__d('EvilCorp/AwsCognito', 'The user does not have a Cognito Username.'));
        }

        $this->CognitoClient->adminEnableUser([
            'UserPoolId' => $this->UserPoolId,
            'Username'   => $entity->aws_cognito_username,
        ]);

        return true;
    }

    public function importMany(array $rows, array $options = [])
    {
        //this method creates/edits an array of entities/arrays

        $_options = [
            'accessibleFields' => [
                'first_name'           => true,
                'last_name'            => true,
                'aws_cognito_username' => true,
                'email'                => true,
            ]
        ];

        $opts = array_merge($_options, $options);

        $entities = [];

        foreach ($rows as $key => $row) {

            if(is_a($row, EntityInterface::class, true)){
                $entities[] = $row;
            }

            $entity = empty($row['id'])
                ? $this->newEntity($row, $opts)
                : $this->patchEntity($this->get($row['id']), $row, $opts);

            $entities[] = $entity;
        }

        return $this->saveMany($entities);
    }

    public function validateMany(array $rows, $max_errors = false, array $options = []): array
    {
        $_options = [
            'accessibleFields' => [
                'first_name'           => true,
                'last_name'            => true,
                'aws_cognito_username' => true,
                'email'                => true,
            ]
        ];

        $opts = array_merge($_options, $options);

        $entities = [];

        $duplicated_check = [
            'email'                => Hash::extract($rows, '{n}.email'),
            'aws_cognito_username' => Hash::extract($rows, '{n}.aws_cognito_username'),
        ];

        $errors_count = 0;

        foreach ($rows as $key => $row) {
            if($max_errors && $errors_count >= $max_errors) break;

            $find_conditions = [
                'aws_cognito_username' => $row['aws_cognito_username']
            ];

            if(is_a($row, EntityInterface::class, true)){
                $entity = $row;
            }else{
                $is_new = !$this->exists($find_conditions);
                $entity = $is_new
                    ? $this->newEntity($row, $opts)
                    : $this->patchEntity(
                        $this->find()->where($find_conditions)->firstOrFail(),
                        $row, $opts);
            }

            //check rules in db
            $this->checkRules($entity);

            //check that the unique fields are also unique within the file
            foreach (['email', 'aws_cognito_username'] as $field) {
                $duplicated_key = array_search($row[$field], $duplicated_check[$field]);
                if($duplicated_key && $duplicated_key !== $key){
                    $entity->setError($field, __d('EvilCorp/AwsCognito', 'This field is duplicated'));
                }
            }

            if($entity->getErrors()) $errors_count++;
            $entities[] = $entity;
        }

        return $entities;
    }

    public function csvDataToAssociativeArray(string $csv_data, array $fields = []): array
    {
        $fields = !empty($fields) ? $fields : [
            'aws_cognito_username',
            'email',
            'first_name',
            'last_name',
        ];

        $rows = explode("\n", $csv_data);
        $parsed_rows = array_map('str_getcsv', $rows);

        array_walk($parsed_rows, function(&$row) use ($fields) {
            $row = array_combine($fields, $row);
        });

        return $parsed_rows;
    }

}
