<?php
namespace EvilCorp\AwsCognito\Model\Entity;

use Cake\ORM\Entity;

class ApiUser extends Entity
{

    protected $_accessible = [
        '*' => true,
        'id' => false,
        'role' => false,

        //cognito fields:
        'aws_cognito_username' => false,
        'aws_cognito_id' => false,
        'email' => false,
    ];

    protected $_hidden = [
        'aws_cognito_username',
        'aws_cognito_id',
    ];

    protected function _getFullName()
    {
        return $this->_properties['first_name'] . ' ' . $this->_properties['last_name'];
    }

}