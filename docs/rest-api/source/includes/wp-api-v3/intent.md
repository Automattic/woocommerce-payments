# Payment intents

The Payment Intents API provides comprehensive functionality for managing payment intents. You can create and manage them seamlessly through its endpoints.


## Create payment intent

_@since v6.6.0_

Create new payment intent.

### Error codes

-   `rest_forbidden` - indicates that the user or application does not have the necessary permissions to perform the requested action.
-   `wcpay_server_error` - Indicates that API had error processing the request. Usually occurs when request params are invalid like order is not found, and similar.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">POST</i>
		<h6>/wp-json/wc/v3/payments/payment_intents</h6>
	</div>
</div>

```shell
curl -X POST https://example.com/wp-json/wc/v3/payments/payment_intents \
	-u consumer_key:consumer_secret \
	-H "Content-Type: application/json" \
	-d '{"payment_method":"<pm_id>","customer":"<customer_id>","order_id":"<order_id>"}'
```

> JSON response example:

```json
{
  "id": "pi_4NxlPtR3eYmZSVZP0PPpwee8",
  "amount": 1023,
  "currency": "USD",
  "created": 1696496578,
  "customer": "cus_OUQoHGzJLw87Tk",
  "payment_method": "pm_2NlT19R3eYmZSVZPRJEvxvwF",
  "status": "succeeded",
  "charge": {
    "id": "ch_4NxlPtR3eYmZSVZP0ZLpKjfI",
    "amount": 1023,
    "application_fee_amount": 62,
    "status": "succeeded",
    "billing_details": {
      "address": {
        "city": "San Francisco",
        "country": "US",
        "line1": "123 Random St",
        "line2": "-",
        "postal_code": "94101",
        "state": "CA"
      },
      "email": "random.email@example.com",
      "name": "John Doe",
      "phone": "5555555555"
    },
    "payment_method_details": {
      "card": {
        "amount_authorized": 1023,
        "brand": "mastercard",
        "capture_before": "",
        "country": "US",
        "exp_month": 5,
        "exp_year": 2030,
        "last4": "4321",
        "three_d_secure": ""
      }
    }
  }
}

```

```json
{
  "code":"rest_forbidden",
  "message":"Sorry, you are not allowed to do that.",
  "data":{
    "status":401
  }
}
```
