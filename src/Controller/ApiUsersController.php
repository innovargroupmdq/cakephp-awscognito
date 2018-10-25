<?php
namespace EvilCorp\AwsCognito\Controller;

use EvilCorp\AwsCognito\Controller\AppController;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;
use EvilCorp\AwsCognito\Controller\Traits\BaseCrudTrait;
use EvilCorp\AwsCognito\Controller\Traits\ImportApiUsersTrait;
use EvilCorp\AwsCognito\Controller\Traits\AwsCognitoTrait;
use Muffin\Footprint\Auth\FootprintAwareTrait;

class ApiUsersController extends AppController
{

    use FootprintAwareTrait;
    use BaseCrudTrait;
    use ImportApiUsersTrait;
    use AwsCognitoTrait;

    public $paginate = [
        'limit' => 100,
        'order' => ['ApiUsers.aws_cognito_username' => 'asc'],
    ];

    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Search.Prg', [
            'actions' => ['index']
        ]);
    }
}