<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('responsiv/pay/invoices') ?>">Invoices</a></li>
        <?php if (isset($formModel)): ?><li class="breadcrumb-item"><a href="<?= Backend::url('responsiv/pay/invoices/preview/'.$formModel->id) ?>"><?= __("View Invoice") ?></a></li><?php endif ?>
        <li class="breadcrumb-item active"><?= e(trans($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<div class="d-flex flex-column h-100">
    <div class="scoreboard" id="<?= $this->getId('scoreboard') ?>">
        <?= $this->makePartial('scoreboard_edit') ?>
    </div>

    <?= $this->formRenderDesign() ?>
</div>
