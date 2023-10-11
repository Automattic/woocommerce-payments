# WooCommerce Payments Core Customer Service

The customer service is available through the `WC_Payments_Customer_Service_API` class, available through `WC_Payments::get_customer_service_api()`:

Example:
```php
$payment_methods = WC_Payments::get_customer_service_api()->get_payment_methods_for_customer( 'cus_XXX', 'card' );
```

## Available methods

- [`get_customer_id_by_user_id()`](#get_customer_id_by_user_id)
- [`create_customer_for_user()`](#create_customer_for_user)
- [`update_customer_for_user()`](#update_customer_for_user)
- [`set_default_payment_method_for_customer()`](#set_default_payment_method_for_customer)
- [`get_payment_methods_for_customer()`](#get_payment_methods_for_customer)
- [`update_payment_method_with_billing_details_from_order()`](#update_payment_method_with_billing_details_from_order)
- [`clear_cached_payment_methods_for_user()`](#clear_cached_payment_methods_for_user)
- [`map_customer_data()`](#map_customer_data)
- [`delete_cached_payment_methods()`](#delete_cached_payment_methods)
- [`get_customer_id_for_order()`](#get_customer_id_for_order)
- [`update_user_customer_id()`](#update_user_customer_id)
- [`add_customer_id_to_user()`](#add_customer_id_to_user)
- [`get_prepared_customer_data()`](#get_prepared_customer_data)

---

#### `get_customer_id_by_user_id()`

Get WCPay customer ID for the given WordPress user ID

__Parameters__
- `$user_id` (`int`) The user ID to look for a customer ID with.

__Return value__
`string?` WCPay customer ID or null if not found.

---
#### `create_customer_for_user()`

Create a customer and associate it with a WordPress user.

__Parameters__
- `$user` (`WP_User`)  User to create a customer for.
- `$customer_data` (`array`) Customer data.

__Return value__
`string` The created customer's ID

---
#### `update_customer_for_user()`

Update the customer details held on the WCPay server associated with the given WordPress user.

__Parameters__

- `$customer_id` (`string`) WCPay customer ID.
- `$user` (`WP_User`) WordPress user.
- `$customer_data` (`array`) Customer data. This should be the return value of [`WC_Payments_Customer_Service_API::get_prepared_customer_data`](#get_prepared_customer_data) below.

__Return value__
`string` The updated customer's ID. Can be different to the ID parameter if the customer was re-created.

---
#### `set_default_payment_method_for_customer()`

Sets a payment method as default for a customer.

__Parameters__
- `$customer_id` (`string`)  The customer ID.
- `$payment_method_id` (`string`) The payment method ID.

---
#### `get_payment_methods_for_customer()`

Gets all payment methods for a customer.

__Parameters__
- `$customer_id` (`string`) The customer ID.
- `$type` (`string`) Type of payment methods to fetch.

__Return value__
`array` Payment methods. 

---
#### `update_payment_method_with_billing_details_from_order()`

Updates a customer payment method.

__Parameters__
- `$payment_method_id` (`string`) The payment method ID.
- `$order` (`WC_Order`)  Order to be used on the update.

__Return value__
`void` This method does not return any value.

---
#### `clear_cached_payment_methods_for_user()`

Clear payment methods cache for a user.

__Parameters__
- `$user_id` (`int`) WC user ID.

__Return value__
`void` This method does not return any value.

---
#### `map_customer_data()`

Given a WC_Order or WC_Customer, returns an array representing a Stripe customer object.

At least one parameter has to not be null.

__Parameters__
- `$wc_order` (`WC_Order`) The Woo order to parse.
- `$wc_customer` (`WC_Customer`) The Woo customer to parse.

__Return value__
`array` Customer data.

public static function map_customer_data( WC_Order $wc_order, WC_Customer $wc_customer )

---
#### `delete_cached_payment_methods()`

Delete all saved payment methods that are stored inside database cache driver.

__Return value__
`void` This method does not return any value.

---
#### `get_customer_id_for_order()`

Get the WCPay customer ID associated with an order, or create one if none found.

__Parameters__
- `$order` (`WC_Order`) WC Order object.

__Return value__
`string|null` WCPay customer ID.

---
#### `update_user_customer_id()`

Updates the given user with the given WooCommerce Payments customer ID.

__Parameters__
- `$user_id` (`int`) The WordPress user ID.
- `$customer_id` (`string`) The WooCommerce Payments customer ID.

__Return value__
`void` This method does not return any value.

---
#### `add_customer_id_to_user()`

Adds the WooComerce Payments customer ID found in the user session to the WordPress user as metadata.

__Parameters__
- `$user_id` (`int`) The WordPress user ID.

__Return value__
`void` This method does not return any value.

---
#### `get_prepared_customer_data()`

Prepares customer data to be used on 'Pay for Order' or 'Add Payment Method' pages.

- Customer data is retrieved from order when on Pay for Order.
- Customer data is retrieved from customer when on 'Add Payment Method'.

__Return value__
`array|null` An array with customer data or nothing.
