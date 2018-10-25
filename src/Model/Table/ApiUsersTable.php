<?php
namespace EvilCorp\AwsCognito\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;
use Cake\Core\Configure;
use EvilCorp\AwsCognito\Model\Entity\ApiUser;
use Exception;
use EvilCorp\AwsCognito\Model\Traits\AwsCognitoSaveTrait;

class ApiUsersTable extends Table
{

    use AwsCognitoSaveTrait;

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

        $this->belongsTo('Creators', [
            'className' => 'Users',
            'foreignKey' => 'created_by',
            'propertyName' => 'creator',
            'dependent' => false,
        ]);

        $this->belongsTo('Modifiers', [
            'className' => 'Users',
            'foreignKey' => 'modified_by',
            'propertyName' => 'modifier',
            'dependent' => false,
        ]);

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

        $local_only = Configure::read('AwsS3.local_only', false);
        $path = $local_only ? 'webroot{DS}files{DS}UserAvatars{DS}{microtime}{DS}' : 'UserAvatars{DS}{microtime}{DS}';
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
                'path' => $path,
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

    public function getRoles()
    {
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
