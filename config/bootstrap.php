<?php
use Cake\Core\Configure;

Configure::load('EvilCorp/AwsCognito.awscognito');
collection((array)Configure::read('AwsCognito.config'))->each(function ($file) {
    Configure::load($file);
});
