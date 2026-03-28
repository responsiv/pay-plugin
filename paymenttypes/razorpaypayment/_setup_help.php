<div class="callout callout-info no-subheader">
    <div class="header">
        <i class="icon-info"></i>
        <h3>How to set up your RazorPay account</h3>
    </div>
    <div class="content">
        <ol>
            <li>Log in to your <a href="https://dashboard.razorpay.com/" target="_blank">RazorPay Dashboard</a></li>
            <li>Navigate to <strong>Account &amp; Settings → API Keys</strong></li>
            <li>Click <strong>Generate Key</strong> to create a new key pair</li>
            <li>Copy the <strong>Key ID</strong> and paste it in the Configuration tab</li>
            <li>Copy the <strong>Key Secret</strong> (shown only once) and paste it in the Configuration tab</li>
        </ol>

        <p><strong>Setting up Webhooks (Optional)</strong></p>
        <p>
            Webhooks allow RazorPay to notify your site when payments are captured, failed, or refunded. This is recommended for production use.
        </p>
        <ol>
            <li>In your RazorPay Dashboard, navigate to <strong>Account &amp; Settings → Webhooks</strong></li>
            <li>Click <strong>Add New Webhook</strong></li>
            <li>
                Enter your webhook URL:
                <code><?= e($formModel->getWebhookUrl()) ?></code>
            </li>
            <li>Create a <strong>Webhook Secret</strong> and paste it in the Configuration tab</li>
            <li>
                Subscribe to the following events:
                <strong>payment.captured</strong>,
                <strong>payment.failed</strong>,
                <strong>refund.processed</strong>
            </li>
            <li>Save the webhook</li>
        </ol>
    </div>
</div>
