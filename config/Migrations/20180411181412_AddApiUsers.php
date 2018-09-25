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
            ->addColumn('avatar_url', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('avatar_file_name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true
            ])
            ->addColumn('avatar_file_dir', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true
            ])
            ->addColumn('avatar_file_size', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true
            ])
            ->addColumn('avatar_file_type', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true
            ])
            ->addColumn('avatar_width', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true
            ])
            ->addColumn('avatar_height', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true
            ])
            ->addColumn('created_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created_by', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified_by', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => true,
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
