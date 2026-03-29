# Setting Up

The Pay plugin provides three CMS components for building a complete invoicing and payment flow: **Invoices** for listing invoices, **Invoice** for viewing a single invoice, and **Payment** for processing payments.

## Invoices Page

The `invoices` component displays a list of invoices belonging to the logged in user. It renders a table with each invoice and provides a link to the invoice detail page.

```
title = "My Invoices"
url = "/account/invoices"

[invoices]
==
{% component 'invoices' %}
```

The default component partial provides a table with columns for invoice number, date, status, and total amount. You can override the partial to customize the layout. The component makes an `invoices` variable available to the page containing the collection of invoices.

The following is an example of a custom invoices list.

```twig
{% if invoices %}
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Status</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            {% for invoice in invoices %}
                <tr>
                    <td>
                        <a href="{{ 'account/invoice'|page({ id: invoice.id }) }}">
                            {{ invoice.getUniqueId }}
                        </a>
                    </td>
                    <td>{{ invoice.invoiced_at }}</td>
                    <td>{{ invoice.status.name }}</td>
                    <td class="text-right">
                        {{ invoice.total|currency({ in: invoice.currency.code }) }}
                    </td>
                </tr>
            {% endfor %}
        </tbody>
    </table>
{% else %}
    <p>No invoices found.</p>
{% endif %}
```

## Invoice Page

The `invoice` component displays a single invoice. It shows a breakdown of the invoice items, along with a total and any tax applied. If the invoice is unpaid, it will link to the payment page.

```
title = "Viewing Invoice"
meta_title = "Viewing Invoice %s"
url = "/account/invoice/:id"

[invoice]
isPrimary = "1"
==
{% component 'invoice' %}
```

The component supports the following properties.

Property | Description
-------- | -----------
**id** | The URL route parameter used for looking up the invoice by its identifier. Default: `{{ :id }}`
**isPrimary** | Used as the default entry point when linking to view an invoice.

The page `meta_title` is dynamically set to include the invoice number. If your page defines `meta_title` with a `%s` placeholder, it will be replaced with the invoice number, for example, "Viewing Invoice #1001".

## Payment Page

The `payment` component is used for submitting payment against an invoice. It displays a payment summary and provides a list of available payment gateways. The invoice is looked up by its unique hash rather than its ID, which allows unauthenticated users to pay invoices via a direct link.

```
title = "Payment"
url = "/payment/:hash"

[payment]
isDefault = "1"
==
{% component 'payment' %}
```

The component supports the following properties.

Property | Description
-------- | -----------
**isDefault** | Used as the default entry point when linking to pay an invoice.

The default component partial handles the complete payment flow including selecting a payment method, displaying the payment form, and showing success or pending messages after payment.

### Payment States

The payment component automatically handles different invoice states:

- **Unpaid** - displays the payment form with available payment methods.
- **Paid** - displays a success message confirming the invoice has been paid.
- **Payment Submitted** - displays a message indicating the payment is being processed. This state prevents customers from paying twice when using gateways that don't confirm immediately.

## Invoice Lookup Form

You can also provide a form for guests to look up invoices without logging in. The following partial demonstrates an invoice lookup that redirects to the payment page.

```
==
<?
function onLookupInvoice()
{
    $data = Request::validate([
        'invoice_number' => 'required',
        'invoice_email' => 'required|email',
    ]);

    $invoice = (new Responsiv\Pay\Models\Invoice)
        ->where('email', $data['invoice_email'])
        ->applyInvoiceNumber($data['invoice_number'])
        ->first();

    if (!$invoice) {
        throw new ValidationException(['invoice_number' => 'Invoice not found']);
    }

    if ($invoice->is_paid) {
        throw new ValidationException(['invoice_number' => 'Invoice already paid. Thanks!']);
    }

    return Redirect::to($this->pageUrl('payment', ['hash' => $invoice->getUniqueHash()]));
}
?>
==
<form data-request="onLookupInvoice" data-request-validate data-request-flash>
    <div class="form-group">
        <input class="form-control" name="invoice_number" type="text" placeholder="Invoice Number" />
        <div class="invalid-feedback" data-validate-for="invoice_number"></div>
    </div>
    <div class="form-group">
        <input class="form-control" name="invoice_email" type="email" placeholder="Your Email Address" />
        <div class="invalid-feedback" data-validate-for="invoice_email"></div>
    </div>
    <button class="btn btn-primary" type="submit">
        Lookup Invoice
    </button>
</form>
```

This form validates the invoice number and email against the database, and redirects to the payment page using the invoice's unique hash. This allows customers to pay without needing an account.
