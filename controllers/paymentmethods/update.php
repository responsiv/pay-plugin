<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('responsiv/pay/paymentmethods') ?>">Payment Methods</a></li>
        <li><?= e(trans($this->pageTitle)) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'layout design-basic']) ?>

        <div class="scoreboard">
            <div data-control="toolbar">
                <div class="scoreboard-item title-value">
                    <h4><?= __("Payment Method") ?></h4>
                    <p class="oc-icon-credit-card"><?= $formModel->driver_name ?></p>
                </div>
            </div>
        </div>

        <div class="layout-row">
            <?= $this->formRender() ?>
        </div>

        <div class="form-buttons pt-3">
            <div data-control="loader-container">
                <?= $this->formRender(['section' => 'buttons']) ?>
            </div>
        </div>

    <?= Form::close() ?>
<?php else: ?>
    <?= $this->formRenderDesign() ?>
<?php endif ?>
