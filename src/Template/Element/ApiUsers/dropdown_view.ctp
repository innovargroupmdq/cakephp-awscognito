<div class="dropdown">
    <button class="btn btn-subtle btn-narrow" id="contextualMenuApiUser" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-option-vertical"></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-right" aria-labelledby="contextualMenuApiUser">

        <li><?= $this->Html->link(
            __d('EvilCorp/AwsCognito', 'Edit API User'),
            ['action' => 'edit', $api_user->id])
            ?>
        </li>
        <?php if($api_user->active): ?>
        <li>
            <?= $this->Form->postLink(
                __d('EvilCorp/AwsCognito', 'Deactivate API User'),
                ['action' => 'deactivate', $api_user->id]
            ) ?>
        </li>
        <?php else: ?>
        <li>
            <?= $this->Form->postLink(
                __d('EvilCorp/AwsCognito', 'Activate API User'),
                ['action' => 'activate', $api_user->id]
            ) ?>
        </li>
        <?php endif; ?>
        <li class="divider"></li>
        <?php if($api_user->aws_cognito_status['code'] === 'CONFIRMED'): ?>
           <li>
            <?= $this->Form->postLink(
                __d('EvilCorp/AwsCognito', 'Reset Password'),
                ['action' => 'resetPassword', $api_user->id],
                [
                    'confirm' => __d('EvilCorp/AwsCognito', 'Are you sure you want to reset the password of # {0}?', $api_user->aws_cognito_username)
                ]
            ) ?>
            </li>
        <?php else: ?>
            <li class="disabled">
                <a href="#"><?= __d('EvilCorp/AwsCognito', 'Reset Password') ?></a>
            </li>
        <?php endif; ?>
        <?php if($api_user->aws_cognito_status['code'] === 'FORCE_CHANGE_PASSWORD'): ?>
            <li><?= $this->Html->link(
                __d('EvilCorp/AwsCognito', 'Resend Invitation Email'),
                ['action' => 'resendInvitationEmail', $api_user->id]
            ) ?></li>
        <?php else: ?>
            <li class="disabled">
                <a href="#"><?= __d('EvilCorp/AwsCognito', 'Resend Invitation Email') ?></a>
            </li>
        <?php endif; ?>
        <li class="divider"></li>
        <li>
            <?= $this->Form->postLink(
                __d('EvilCorp/AwsCognito', 'Delete API User'),
                ['action' => 'delete', $api_user->id],
                [
                    'confirm' => __d('EvilCorp/AwsCognito', 'Are you sure you want to delete the API User "{0}"?', $api_user->full_name)
                ]
            ) ?>
        </li>

    </ul>
</div>