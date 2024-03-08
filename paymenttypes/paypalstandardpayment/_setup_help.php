<?php
    $autoreturnUrl = $formModel->getAutoreturnUrl();
?>
<div class="callout callout-info no-subheader">
    <div class="header">
        <i class="icon-info"></i>
        <h3>How to set up your PayPal account</h3>
    </div>
    <div class="content">
        <ol>
            <li>Log in to your PayPal account</li>
            <li>Click the <strong>Profile > Profile and settings</strong> link in the top menu</li>
            <li>Click the Update button next to <strong>Website preferences</strong>, in the <strong>My sellings tools</strong> tab</li>
            <li>Under <strong>Auto Return for Website Payments</strong>, click the <strong>On</strong> radio button</li>
            <li>For the <strong>Return URL</strong>, enter <a href="<?= $autoreturnUrl ?>" onclick="return false"><?= e($autoreturnUrl) ?></a></li>
            <li>Under <strong>Payment Data Transfer</strong>, click the <strong>On</strong> radio button</li>
            <li>Click <strong>Save</strong></li>
            <li>After saving  you should see the Payment Data Transfer token which you must specify in the Configuration tab</li>
        </ol>
    </div>
</div>
