<?php
namespace EvilCorp\AwsCognito\Controller\Api;

use EvilCorp\AwsCognito\Controller\AppController as BaseController;

class AppController extends BaseController
{
	public function initialize()
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Auth');

        $this->loadComponent('EvilCorp/AwsApiGateway.ApiRequest');

        $this->Auth->config('authenticate', [
            'EvilCorp/AwsCognito.AwsCognitoJwt' => [
            	'userModel' => 'EvilCorp/AwsCognito.ApiUsers'
            ]
        ]);

        $this->Auth->config('storage', 'Memory');
        $this->Auth->config('unauthorizedRedirect', false);
        $this->Auth->config('loginAction', false);
        $this->Auth->config('loginRedirect', false);
    }

}
