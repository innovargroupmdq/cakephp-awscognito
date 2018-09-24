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
                <?= $this->element('Layout/search',     ['placeholder'=>__('Search API Users')]); ?>
                <?= $this->element('Layout/button-add', ['label'=>__('New API User')]); ?>
                <div class="btn-group" role="group">
                    <?= $this->Html->link(
                         __('Import API Users'),
                        ['action' => 'import'],
                        ['class'=>'btn btn-default', 'escape' => false]
                    )
                    ?>
                </div>
                <?= $this->element('Layout/pager'); ?>
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
                            <div class="dropdown">
                                <button class="btn btn-subtle btn-narrow" id="dropdown-<?= $api_user->id ?>" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="glyphicon glyphicon-option-vertical"></span>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="dropdown-<?= $api_user->id ?>">
                                   <li><?= $this->Html->link(__('View'), ['action' => 'view', $api_user->id]) ?></li>
                                   <li><?= $this->Html->link(__('Edit'), ['action' => 'edit', $api_user->id]) ?></li>
                                   <li class="divider"></li>
                                   <li><?= $this->Form->postLink(
                                        __('Delete API User'),
                                        ['action' => 'delete', $api_user->id],
                                        [
                                            'confirm' => __('Are you sure you want to delete # {0}?', $api_user->aws_cognito_username)
                                        ]
                                    ) ?></li>
                                </ul>
                            </div>
                            
                        </td>
                        <td><?= h($api_user->aws_cognito_username) ?>
                            <?php if(!$api_user->active): ?>
                                <span class="label label-danger">inactivo</span>
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
