<div class="callout callout-info no-subheader">
    <div class="header">
        <i class="icon-info"></i>
        <h3>How to set up your PayPal account</h3>
    </div>
    <div class="content">
        <ol>
            <li>Log in to your <a href="https://developer.paypal.com/" target="_blank">PayPal Developer account</a></li>
            <li>Click the <strong>My Apps &amp; Credentials</strong> link in the menu</li>
            <li>Toggle the <strong>Live / Sandbox</strong> option depending on your requirements</li>
            <li>Click <strong>Create App</strong> and fill in the required details</li>
            <li>From here you can access your REST API credentials</li>
            <li>Copy the <strong>Client ID</strong> and paste it in the Configuration tab</li>
            <li>Copy the <strong>Secret Key</strong> and paste it in the Configuration tab</li>
        </ol>

        <p><strong>Setting up Webhooks (Optional)</strong></p>
        <p>
            Webhooks allow PayPal to notify your site when payments are completed, refunded or denied. This is recommended for production use.
        </p>
        <ol>
            <li>In your PayPal Developer Dashboard, navigate to your app and click <strong>Webhooks</strong></li>
            <li>Click <strong>Add Webhook</strong></li>
            <li>
                Enter your webhook URL:
                <code><?= e($formModel->getWebhookUrl()) ?></code>
            </li>
            <li>
                Subscribe to the following events:
                <strong>PAYMENT.CAPTURE.COMPLETED</strong>,
                <strong>PAYMENT.CAPTURE.REFUNDED</strong>,
                <strong>PAYMENT.CAPTURE.DENIED</strong>
            </li>
            <li>Save, then copy the generated <strong>Webhook ID</strong> and paste it in the Configuration tab</li>
        </ol>
    </div>
</div>
