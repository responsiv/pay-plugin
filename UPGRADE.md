# Upgrading from PAy v1 to v2

This guide can be used to help migrate from Responsiv.Pay v1 to v2. Some theme changes are required to since there are new components.

## Upgrade Instructions

1. Run `php artisan plugin:install responsiv.pay` to request the latest version (you do not need to uninstall v1 first).

1. Migrate user data using `php artisan pay:migratev1` (one-way function).

1. Continue using this plugin as normal.

## Key Differences

- All amounts are now stored in cents rather than decimals, this means `$1.00` is stored as `100`.

- Invoice item discount are no longer stored as percentage, but as a fixed amount.

## Key Similarities

- ...

## Breaking Changes

- ...
