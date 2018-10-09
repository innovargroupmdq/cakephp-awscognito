<div class="APIUsers-form container">
    <?= $this->Form->create($api_user) ?>
    <div class="card">
        <div class="header">
            <h3 class="title"><?= __d('EvilCorp/AwsCognito', 'Change API User Email') ?></h3>
        </div>
        <div class="content">
            <div class="form-group">
                <label class="col-sm-3 control-label">
                    <?= __d('EvilCorp/AwsCognito', 'Aws Cognito Username') ?>
                </label>
                <div class="col-sm-9">
                    <p class="form-control-static">
                        <?= $api_user->aws_cognito_username ?>
                    </p>
                </div>
            </div>
            <?= $this->Form->control('email'); ?>
            <?= $this->Form->control('require_verification', [
                'label' => __d('EvilCorp/AwsCognito', 'Require Verification'),
                'type' => 'checkbox',
                'checked' => true,
                'help' => __d('EvilCorp/AwsCognito', 'Sends a code to this address. The user should use this to verify their account.')
            ]); ?>

        <?= $this->Form->submit(__d('EvilCorp/AwsCognito', 'Save changes'), ['class'=>'btn btn-lg btn-primary']); ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>
