# Upgrading from Pay v1 to v2

This guide can be used to help migrate from Responsiv.Pay v1 to v2. Some theme changes are required to since there are new components. Mostly amounts are stored in their base units instead of decimals.

**Please make sure you have a database and website backup before performing the upgrade.**

## Upgrade Instructions

1. **Make sure you are running October CMS v3.6 or greater.**

1. Run `php artisan plugin:install responsiv.pay` to request the latest version (you do not need to uninstall v1 first).

1. Continue using this plugin as normal.

## Key Differences

- All amounts are now stored in cents rather than decimals, this means `$1.00` is stored as `100`.

- Invoice item discount are no longer stored as percentage, but as a fixed amount.

## Previewing Changes

We recommend installing the `Responsiv.Agency` theme to demonstrate the latest functionality.

- https://github.com/responsiv/agency-theme

### Feedback

If there are any changes you would like us to include to make upgrading easier, let us know and we can accommodate them in a new release.

Thanks for reading.
