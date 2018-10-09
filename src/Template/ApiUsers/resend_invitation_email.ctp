<?php
/**
  * @var \App\View\AppView $this
  */
?>


<div class="APIUsers-form container">
    <?= $this->Form->create($api_user) ?>
    <div class="card">
        <div class="header">
            <h3 class="title"><?= __d('EvilCorp/AwsCognito', 'API User') ?>: <?= __d('EvilCorp/AwsCognito', 'Resend Invitation Email') ?></h3>
        </div>
        <div class="content">
        <?php
            echo $this->Form->control('email');
        ?>
        <?= $this->Form->submit(__d('EvilCorp/AwsCognito', 'Resend Invitation Email'), ['class'=>'btn btn-lg btn-primary']); ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>
