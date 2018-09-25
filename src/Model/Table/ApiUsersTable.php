<?php
namespace EvilCorp\AwsCognito\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;
use Cake\Core\Configure;
use EvilCorp\AwsCognito\Model\Entity\ApiUser;
use Exception;

class ApiUsersTable extends Table
{

    protected $searchQueryFields = [
        'ApiUsers.aws_cognito_username',
        'ApiUsers.email',
        'ApiUsers.first_name',
        'ApiUsers.last_name',
    ];

	public function initialize(array $config)
    {
    	parent::initialize($config);

        $this->setTable('api_users');
        $this->setDisplayField('full_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'modified_at' => 'always',
                ]
            ]
        ]);

        $this->addBehavior('Muffin/Footprint.Footprint', [
            'events' => [
                'Model.beforeSave' => [
                    'created_by' => 'new',
                    'modified_by' => 'always',
                ]
            ]
        ]);

        $this->addBehavior('EvilCorp/AwsCognito.AwsCognito', Configure::read('AwsCognito'));
        $this->addBehavior('EvilCorp/AwsCognito.ImportApiUsers');
        $this->addBehavior('EvilCorp/AwsS3Upload.AwsS3Upload', [
            'avatar_file_name' => [
                'fields' => [
                    'dir'          => 'avatar_file_dir',
                    'size'         => 'avatar_file_size',
                    'type'         => 'avatar_file_type',
                    'url'          => 'avatar_url',
                    'image_width'  => 'avatar_width',
                    'image_height' => 'avatar_height'
                ],
                'path' => 'UserAvatars{DS}{microtime}{DS}',
                'images_only' => true
            ]
        ]);

        $this->addBehavior('Search.Search');
        $this->searchManager()
            ->value('active')
            ->add('q', 'Search.Callback', [
                'callback' => function ($query, $args, $filter){

                    $q = trim($args['q']);
                    $conditions = [];
                    $comparison = 'LIKE';

                    foreach ($this->searchQueryFields as $field) {
                        //add single value
                        $left = $field . ' ' . $comparison;
                        $valueConditions = [
                            [$left => "%$q%"]
                        ];

                        //add all words
                        $words = explode(" ", $q);
                        foreach ($words as $word) {
                            $right = "%$word%";
                            $valueConditions[] = [$left => $right];
                        }

                        if (!empty($valueConditions)) {
                            $conditions[] = ['OR' => $valueConditions];
                        }

                    }

                    $query->andWhere(['OR' => $conditions]);

                    return $query;
                }
            ]);
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

        $validator
            ->allowEmpty('avatar_file_name');

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

}
