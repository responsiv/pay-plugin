<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('responsiv/pay/taxes') ?>"><?= __("Taxes") ?></a></li>
        <li><?= e(trans($this->pageTitle)) ?></li>
    </ul>
<?php Block::endPut() ?>

<?= $this->formRenderDesign() ?>
