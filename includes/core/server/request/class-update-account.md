# `Update_Account` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Update_Account` class is used to construct the request for creating an intention.

## Parameters

| Parameter                         | Setter                                                                           | Immutable | Required | Default value |
|-----------------------------------|----------------------------------------------------------------------------------|:---------:|:--------:|:-------------:|
| `statement_descriptor`            | `set_statement_descriptor( string $statement_descriptor )`                       |     -     |    -     |       -       |
| `business_name`                   | `set_business_name( string $business_name )`                                     |     -     |    -     |       -       |
| `business_url`                    | `set_business_url( string $business_url )`                                       |     -     |    -     |       -       |
| `business_support_address`        | `set_business_support_address( string $business_support_address )`               |     -     |    -     |       -       |
| `business_support_email`          | `set_business_support_email( string $business_support_email )`                   |     -     |    -     |       -       |
| `business_support_phone`          | `set_business_support_phone( string $business_support_phone )`                   |     -     |    -     |       -       |
| `branding_logo`                   | `set_branding_logo( string $branding_logo )`                                     |     -     |    -     |       -       |
| `branding_icon`                   | `set_branding_icon( string $branding_icon )`                                     |     -     |    -     |       -       |
| `branding_primary_color`          | `set_branding_primary_color( string $branding_primary_color )`                   |     -     |    -     |       -       |
| `branding_secondary_color`        | `set_branding_secondary_color( string $branding_secondary_color )`               |     -     |    -     |       -       |
| `deposit_schedule_interval`       | `set_deposit_schedule_interval( string $deposit_schedule_interval )`             |     -     |    -     |       -       |
| `deposit_schedule_weekly_anchor`  | `set_deposit_schedule_weekly_anchor( string $deposit_schedule_weekly_anchor )`   |     -     |    -     |       -       |
| `deposit_schedule_monthly_anchor` | `set_deposit_schedule_monthly_anchor( string $deposit_schedule_monthly_anchor )` |     -     |    -     |       -       |
| `locale`                          | `set_locale( string $locale )`                                                   |     -     |    -     |       -       |

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_update_account_settings`
- Arguments: None.

## Example:

```php
$request = Update_Account::create();

$request->set_statement_descriptor( $statement_descriptor );
$request->set_business_name( $business_name );
$request->set_business_url( $business_url );
$request->set_business_support_address( $business_support_address );
$request->set_business_support_email( $business_support_email );
$request->set_business_support_phone( $business_support_phone );
$request->set_branding_logo( $branding_logo );
$request->set_branding_icon( $branding_icon );
$request->set_branding_primary_color( $branding_primary_color );
$request->set_branding_secondary_color( $branding_secondary_color );
$request->set_deposit_schedule_interval( $deposit_schedule_interval );
$request->set_deposit_schedule_weekly_anchor( $deposit_schedule_weekly_anchor );
$request->set_deposit_schedule_monthly_anchor( $deposit_schedule_monthly_anchor );
$request->set_locale( $locale );

$request->send( 'wcpay_update_account_settings' );
```
