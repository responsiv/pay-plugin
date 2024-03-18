<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('responsiv/pay/invoices') ?>">Invoices</a></li>
        <li class="breadcrumb-item active"><?= e(trans($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<div class="d-flex flex-column h-100">
    <div class="scoreboard" id="<?= $this->getId('scoreboard') ?>">
        <?= $this->makePartial('scoreboard_edit') ?>
    </div>

    <?= $this->formRenderDesign() ?>
</div>
