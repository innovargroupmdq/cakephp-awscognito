<?php
/**
  * @var \App\View\AppView $this
  */
?>


<div class="APIUsers-form container">
    <?= $this->Form->create($api_user) ?>
    <div class="card">
        <div class="header">
            <h3 class="title"><?= __('Add API User') ?></h3>
        </div>
        <div class="content">
        <?php
            echo $this->Form->control('aws_cognito_username');
            echo $this->Form->control('email');
        ?>
        <?php
            echo $this->Form->control('active', [
                'type' => 'checkbox',
                'label' => __('Active'),
                'default' => true
            ]);
        ?>
        <hr>
        <?php
            echo $this->Form->control('first_name');
            echo $this->Form->control('last_name');
            echo $this->Form->control('role', [
                'options' => $roles
            ]);
         ?>
        <?= $this->Form->submit(__('Save changes'), ['class'=>'btn btn-lg btn-primary']); ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>
