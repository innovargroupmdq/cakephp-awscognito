<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

Router::plugin(
    'EvilCorp/AwsCognito',
    ['path' => '/aws-cognito'],
    function (RouteBuilder $routes) {
        $routes->connect('/api/api-users/:action', [
            'controller' => 'ApiUsers',
            'prefix'     => 'Api',
            'action'     => ':action'
        ]);
        $routes->fallbacks(DashedRoute::class);
    }
);
