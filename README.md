# Payment plugin

Payment system for October. Allows the generation of invoices and use of payment gateways, supplied by this plugin or others.

This plugin requires the following plugins:

- [Responsiv.Currency](http://octobercms.com/plugin/responsiv-currency)
- [RainLab.User](http://octobercms.com/plugin/rainlab-user)
- [RainLab.UserPlus](http://octobercms.com/plugin/rainlab-userplus)
- [RainLab.Location](http://octobercms.com/plugin/rainlab-location)

### Invoices page

The invoices page is used to list all the invoices owned by the logged in user. It displays a table with each invoice and provides a link to the invoice page.

    title = "My Invoices"
    url = "/account/invoices"

    [invoices]
    ==
    {% component 'invoices' %}

### Invoice page

The invoice page is used to display a single invoice. It displays a table containing a breakdown of the invoice items, along with a total and any tax applied. If the invoice is unpaid, it will link to the payment page.

    title = "Viewing Invoice"
    meta_title = "Viewing Invoice %s"
    url = "/account/invoice/:id"

    [invoice]
    isPrimary = "1"
    ==
    {% component 'invoice' %}

### Payment page

The payment page is used for submitting payment against an invoice. Like the invoice, it will display a payment summary, but it also provides a list of payment gateways that can be used for paying.

    title = "Payment"
    url = "/payment/:hash"

    [payment]
    ==
    {% component 'payment' %}
