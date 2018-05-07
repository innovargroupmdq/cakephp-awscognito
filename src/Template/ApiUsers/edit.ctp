<div class="APIUsers-form container">
    <?= $this->Form->create($api_user) ?>
    <div class="card">
        <div class="header">
            <h3 class="title"><?= __('Edit API User') ?></h3>
        </div>
        <div class="content">
            <div class="form-group">
                <label class="col-sm-3 control-label">
                    <?= __('Aws Cognito Username') ?>
                </label>
                <div class="col-sm-9">
                    <p class="form-control-static">
                        <?= $api_user->aws_cognito_username ?>
                    </p>
                </div>
            </div>

            <?= $this->Form->control('email'); ?>
            <?= $this->Form->control('active'); ?>
            <hr>
            <?= $this->Form->control('first_name'); ?>
            <?= $this->Form->control('last_name'); ?>
            <?= $this->Form->control('force_geoposition'); ?>

        <?= $this->Form->submit(__('Save changes'), ['class'=>'btn btn-lg btn-primary']); ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>
