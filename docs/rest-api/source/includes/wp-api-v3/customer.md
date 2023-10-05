# Customer

The Customers API endpoints provide access to customer data. This includes payment methods and other key information useful for your application.


## Get customer's payment methods

_@since v6.6.0_

Return all customer's payment methods.

### Error codes

-   `rest_forbidden` - indicates that the user or application does not have the necessary permissions to perform the requested action.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">
    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/customers/&lt;customer_id&gt;/payment_methods</h6>
  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/customers/cus_123456/payment_methods \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
[
  {
    "id": "pm_1AxXc2a5dGhIZQYPlaLbKj1Z",
    "type": "card",
    "billing_details": {
      "address": {
        "city": "Los Angeles",
        "country": "US",
        "line1": "123 Anywhere St",
        "line2": null,
        "postal_code": "90002",
        "state": "CA"
      },
      "email": "john.doe@example.com",
      "name": "John Doe",
      "phone": null
    },
    "card": {
      "brand": "visa",
      "last4": "1122",
      "exp_month": 10,
      "exp_year": 2028
    }
  },
  {
    "id": "pm_2BcYd3e6hKjZLQWQmMnOjK45",
    "type": "card",
    "billing_details": {
      "address": {
        "city": "New York",
        "country": "US",
        "line1": "456 Broadway Ave",
        "line2": null,
        "postal_code": "10012",
        "state": "NY"
      },
      "email": "jane.smith@example.com",
      "name": "Jane Smith",
      "phone": null
    },
    "card": {
      "brand": "mastercard",
      "last4": "3344",
      "exp_month": 12,
      "exp_year": 2027
    }
  }
]

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
