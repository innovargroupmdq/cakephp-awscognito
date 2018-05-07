<?php
/**
  * @var \App\View\AppView $this
  */
use Cake\Utility\Hash;
?>

<div class="container">
    <h1 class="page-title"><?= h($api_user->username) ?></h2>
</div>

<div class="APIUsers-view container">
            <div class="card">
                <div class="header">
                    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $api_user->id], ['class'=>'pull-right btn btn-subtle']) ?>
                    <?= $this->Form->postLink(
                        __('Delete API User'),
                        ['action' => 'delete', $api_user->id],
                        [
                            'class' => 'btn btn-subtle',
                            'confirm' => __('Are you sure you want to delete # {0}?', $api_user->aws_cognito_username)
                        ]
                    ) ?>
                    <?php if($cognito_user['UserStatus'] === 'CONFIRMED'): ?>
                        <?= $this->Form->postLink(
                            __('Reset Password'),
                            ['action' => 'resetPassword', $api_user->id],
                            [
                                'class' => 'btn btn-subtle',
                                'confirm' => __('Are you sure you want to reset the password of # {0}?', $api_user->aws_cognito_username)
                            ]
                        ) ?>
                    <?php elseif($cognito_user['UserStatus'] === 'FORCE_CHANGE_PASSWORD'): ?>
                        <?= $this->Html->link(
                            __('Resend Invitation Email'),
                            ['action' => 'resendInvitationEmail', $api_user->id],
                            ['class' => 'btn btn-subtle']
                        ) ?>
                    <?php endif; ?>
                </div>
                <div class="content">
                    <table class="table table-striped">
                        <tr>
                            <th scope="row"><?= __('Id') ?></th>
                            <td><?= h($api_user->id) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Aws Cognito Username') ?></th>
                            <td><?= h($api_user->aws_cognito_username) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Email') ?></th>
                            <td><?= h($api_user->email) ?></td>
                        </tr>
                        <tr>
                            <th><?= __('Email Verified') ?></th>
                            <td><?= $cognito_user['Attributes']['email_verified'] ? __('Yes') : __('No'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('First Name') ?></th>
                            <td><?= h($api_user->first_name) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Last Name') ?></th>
                            <td><?= h($api_user->last_name) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Role') ?></th>
                            <td><?= h($api_user->role) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Created') ?></th>
                            <td><?= h($api_user->created) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Modified') ?></th>
                            <td><?= h($api_user->modified) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= __('Active') ?></th>
                            <td><?= $api_user->active ? __('Yes') : __('No'); ?></td>
                        </tr>
                    </table>


                </div>
            </div>


</div>