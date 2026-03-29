# Introduction

The Pay plugin brings payment services to October CMS, allowing your users to view and pay their own invoices. It provides CMS components for listing invoices, viewing invoice details, and processing payments through configurable payment gateways.

## Requirements

This plugin requires the following plugins:

- **Responsiv.Currency** - for currency formatting and conversion.
- **RainLab.User** - for user authentication and invoice ownership.
- **RainLab.UserPlus** - for extended user profile fields (address, company, etc).
- **RainLab.Location** - for country and state selection used in payment method filtering.

## Demo Theme

To get started, we recommend installing this plugin with the `Responsiv.Agency` theme to see a working demonstration of all the components.

- https://github.com/responsiv/agency-theme

The agency theme includes an invoice lookup form on the homepage, an authenticated account area for viewing invoices, and both authenticated and public payment pages.

## Payment Methods

Before invoices can be paid, you need to configure at least one payment method. Navigate to **Settings > Payment Methods** in the administration panel to set up a payment gateway. Each payment method is powered by a payment type class (such as PayPal or Stripe) and can be configured with API credentials and other settings.

When a payment method is created, a corresponding partial is generated in the active theme under the **partials/pay** directory. This partial contains the payment form displayed to customers and can be customized for styling.
