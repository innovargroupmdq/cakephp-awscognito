<?php
namespace EvilCorp\AwsCognito\Controller\Api;

use EvilCorp\AwsCognito\Controller\Api\AppController;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;

class ApiUsersController extends AppController
{
    public function profile()
    {
        $api_user = $this->ApiUsers->get($this->Auth->user('id'));

        $this->set('profile', $api_user);
    }
}