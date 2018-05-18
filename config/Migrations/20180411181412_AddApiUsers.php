<?php
use Migrations\AbstractMigration;

class AddApiUsers extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $this->table('api_users')
            ->addColumn('aws_cognito_id', 'uuid', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('aws_cognito_username', 'string', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('email', 'string', [
                'limit' => 255,
                'default' => null,
                'null' => false,
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('role', 'string', [
                'limit' => 50,
                'default' => null,
                'null' => false,
            ])
            ->addColumn('first_name', 'string', [
                'limit' => 50,
                'default' => null,
                'null' => true,
            ])
            ->addColumn('last_name', 'string', [
                'limit' => 50,
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])

            ->addIndex(['aws_cognito_id'], [
                'name' => 'api_users_aws_cognito_id',
                'unique' => true
            ])
            ->addIndex(['aws_cognito_username'], [
                'name' => 'api_users_aws_cognito_username',
                'unique' => true
            ])
        ->create();
    }
}