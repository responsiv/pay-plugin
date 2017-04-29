# Upgrade guide

- [Upgrading to 1.1 from 1.0](#upgrade-1.1)

There are some housekeeping items that have been addressed in this version.

The `InvoiceStatus::getByCode` method has been renamed to `InvoiceStatus::getFromCode`.

These methods have been removed from the `InvoiceStatus` model:

- getStatusDraft
- getStatusApproved
- getStatusPaid
- getStatusVoid

Use the `getFromCode` method instead, eg:

    InvoiceStatus::getFromCode(InvoiceStatus::STATUS_DRAFT)
