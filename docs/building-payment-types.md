# Building Payment Types

Payment types are merchant gateways defined as classes located in the **paymenttypes** directory of this plugin. You can create your own plugins with this directory or place them inside the `app` directory.

```
plugins/
  acme/
    myplugin/
      paymenttypes/
        paypalstandard/      <=== Class Directory
          fields.yaml        <=== Field Configuration
        PayPalStandard.php   <=== Class File
      Plugin.php
```

These instructions can be used to create your own payment type classes to integrate with specific gateways, and a plugin can be host to many payment types, not just one.

## Payment Type Definition

Payment type classes should extend the `Responsiv\Pay\Classes\GatewayBase` class, which is an abstract PHP class containing all the necessary methods for implementing a payment type. By extending this base class, you can add the necessary features, such as communicating with the the payment gateway API.

The payment type from the next example should be defined in the **plugins/acme/myplugin/paymenttypes/PayPalPayment.php** file. Aside from the PHP file, payment types can also have a directory that matches the PHP file name. If the class name is **PayPalPayment.php** then the corresponding directory name is **paypalstandard**. These class directories can contain partials and form field configuration used by the payment type.

```php
class PayPalPayment extends GatewayBase
{
    public function driverDetails()
    {
        return [
            'name' => 'PayPal',
            'description' => 'Accept payments using the PayPal REST API.'
        ];
    }

    public function processPaymentForm($data, $invoice)
    {
        // ...
    }
}
```

The `driverDetails` method is required. The method should return an array with two keys: name and description. The name and description are display in the administration panel when setting up the payment type.

Payment types must be registered by overriding the `registerPaymentGateways` method inside the plugin registration file (Plugin.php). This tells the system about the payment type and provides a short code for referencing it.

The following registers the `PayPalPayment` class with the code **paypal* so it is ready to use.

```php
public function registerPaymentGateways()
{
    return [
        \Responsiv\Pay\PaymentTypes\PayPalPayment::class => 'paypal',
    ];
}
```

## Building the Payment Configuration Form

By default, the payment type will look for its form field definitions as a file **fields.yaml** in the class directory. In this file, you can define [form fields and tabs](https://docs.octobercms.com/3.x/element/form-fields.html) used by the payment type configuration form.

When a payment type is selected for configuration, it is stored as a `Responsiv\Pay\Models\PaymentMethod` instance. All field values are saved automatically to this model and are available inside the processing code.

The configuration form fields can add things like API usernames and passwords used for the payment gateway. The following might be stored in the **plugins/acme/myplugin/paymenttypes/paypalpayment/fields.yaml** file.

```yaml
fields:
    client_id:
        label: Client ID
        comment: PayPal client ID to identify your app.
        tab: Configuration

    client_secret:
        label: Secret Key
        comment: PayPal client secret to authenticate with the client ID. Keep this secret safe.
        tab: Configuration
        type: sensitive
```

### Initializing the Configuration Form

You may initialize the values of the configuration form fields by overriding the `initDriverHost` method in the payment type class definition. The method takes a `$host` argument as the model object, which can be used to set the attribute values matching the form fields.

The following example checks if the model is newly created using `$host->exists` and sets some default values.

```php
public function initDriverHost($host)
{
    if (!$host->exists) {
        $host->name = 'PayPal';
        $host->test_mode = true;
    }
}
```

### Validating the Configuration Form

Once you have the form fields specified, you may wish to validate their input. Override the `validateDriverHost` method inside the class to implement validation logic. The method takes a `$host` argument as a model object with the attributes matching those found in the form field definition.

Throw the `\ValidationException` exception to trigger a validation error message. This exception takes an array with the field name as a key and the error message as the value. The message should use the `__()` helper function to enable localization for the message.

```php
public function validateDriverHost($host)
{
    if (!$host->client_id) {
        throw new \ValidationException(['client_id' => __("Please specify a Client ID")]);
    }
}
```

For simple validation rules, you can apply them to the model using the `initDriverHost` method.

```php
public function initDriverHost($host)
{
    $host->rules['client_id'] = 'required';
    $host->rules['client_secret'] = 'required';
}
```

## Payment Form Templates

Each payment type provide a template for the payment form partial containing the payment form that will be displayed on the frontend. When a new payment type is set up, it will automatically create a new partial in the active theme based on the template.

An example of a payment form template might be **plugins/acme/myplugin/paymenttypes/paypalpayment/payment-form.htm**

The partial name generated in the theme is based on the class name of the payment type, for example, **pay/paypalstandard.htm**. From here you can modify the payment partial for styling and customization.

The partial is created for the payment type only if it cannot find the partial already. This means the contents are never updated automatically, allowing for customization. If you wish to update the payment form partial contents, you should delete the corresponding partial manually and then navigate to the **Payment Methods** page to recreate it.

There are two ways to implement the payment form, either using a redirection method or using a client-side method. The integration will depend on the recommended approach from the payment gateway.

### Redirection Method

Using the redirection method, when the user clicks the Pay button, they are redirected away from the website to a secure payment form provided by the payment gateway and hosted with the payment provider. Usually the redirection happens using a HTML form tag where the URL points to the payment gateway directly. The form contains hidden fields providing the necessary information to process the payment, such as the order number, amount and other parameters.

> **Note**: View the `Responsiv\Pay\PaymentTypes\StripePayment` class for an example of a redirection payment type.

### Client-side Method

Using the client-side method, the payment form is handled using custom JavaScript. When the user clicks Pay, the request is submitted back to the server using a locally registered API endpoint, which communicates to the payment gateway via a REST API or equivalent. The response given with JSON And is processed by the JavaScript code. If everything is successful the `onPay` AJAX handler is called to redirect to the confirmation page.

> **Note**: View the `Responsiv\Pay\PaymentTypes\PayPalPayment` class for an example of a redirection payment type.
