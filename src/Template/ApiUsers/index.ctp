<?php
/**
  * @var \App\View\AppView $this
  */
    use Cake\Utility\Hash;
?>
<div class="APIUsers-index container-fluid">
    <div class="page-title">
        <h2><?= __('API Users') ?></h2>
    </div>

    <div class="card">
        <div class="header">
            <div class="btn-toolbar">
                <div class="btn-group" role="group">
                    <?= $this->Html->link(
                         __('Import API Users'),
                        ['action' => 'import'],
                        ['class'=>'btn btn-default', 'escape' => false]
                    )
                    ?>
                </div>
            </div>
        </div>

        <div class="content table-responsive table-full-width">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col" class="actions"></th>
                        <th scope="col" class="field-aws_cognito_username"><?= $this->Paginator->sort('aws_cognito_username') ?></th>
                        <th scope="col" class="field-email"><?= $this->Paginator->sort('email') ?></th>
                        <th scope="col" class="field-first_name"><?= $this->Paginator->sort('first_name') ?></th>
                        <th scope="col" class="field-last_name"><?= $this->Paginator->sort('last_name') ?></th>
                        <th scope="col" class="field-role"><?= $this->Paginator->sort('role') ?></th>
                        <th scope="col" class="field-created"><?= $this->Paginator->sort('created') ?></th>
                        <th scope="col" class="field-modified"><?= $this->Paginator->sort('modified') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_users as $api_user): ?>
                    <tr>
                        <td class="actions">
                            <?= $this->element('EvilCorp/AwsCognito.ApiUsers/dropdown_index', ['api_user' => $api_user]) ?>
                        </td>
                        <td><?= h($api_user->aws_cognito_username) ?>
                            <?php if(!$api_user->active): ?>
                                <span class="label label-danger pull-right">inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($api_user->email) ?></td>
                        <td><?= h($api_user->first_name) ?></td>
                        <td><?= h($api_user->last_name) ?></td>
                        <td><?= h($api_user->role) ?></td>
                        <td><?= h($api_user->created) ?></td>
                        <td><?= h($api_user->modified) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
