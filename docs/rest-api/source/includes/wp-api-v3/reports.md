# Reports

The Reports API allows you to obtain detailed records of transactions and authorizations.

## List transactions

_@since 6.5.0_

Fetch a detailed overview of transactions.
### Error codes

-   `rest_forbidden` - Unauthenticated
-   `rest_invalid_param` - Invalid request parameter
-   `wcpay_server_error` - Unexpected server error

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/reports/transactions</h6>
	</div>
</div>

### Optional parameters

- `date_before`: Filter transactions before this date.
- `date_after`: Filter transactions after this date.
- `date_between`: Filter transactions between these dates.
- `order_id`: Filter transactions based on the associated order ID.
- `deposit_id`: Filter transactions based on the associated deposit ID.
- `customer_email`: Filter transactions based on the customer email.
- `payment_method_type`: Filter transactions based on the payment method used.
- `type`: Filter transactions where type is a specific value.
- `match`: Match filter for the transactions.
- `user_timezone`: Include timezone into date filtering.
- `page`: Page number.
- `per_page`: Page size.
- `sort`: Field on which to sort.
- `direction`: Direction on which to sort.

```shell
curl -X POST https://example.com/wp-json/wc/v3/payments/reports/transactions?page=1 \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
[
  {
    "date":"2023-10-01 15:00:00",
    "transaction_id":"txn_1234567890abcdef",
    "payment_id":"pi_1234567890abcdef",
    "channel":"online",
    "payment_method":{
      "type":"card",
      "id":"pm_1234567890abcdef"
    },
    "type":"charge",
    "transaction_currency":"USD",
    "amount":1000,
    "exchange_rate":1.00,
    "deposit_currency":"USD",
    "fees":20,
    "customer":{
      "name":"John Doe",
      "email":"john.doe@example.com",
      "country":"US"
    },
    "net_amount":980,
    "order_id":12345,
    "risk_level":1,
    "deposit_date":"2023-10-02 15:00:00",
    "deposit_id":"dp_1234567890abcdef",
    "deposit_status":"completed"
  }
]
```

## List transaction

_@since 6.5.0_

Fetch a detailed overview of single transaction.
### Error codes

-   `rest_forbidden` - Unauthenticated
-   `wcpay_server_error` - Unexpected server error

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/reports/transactions/&lt;transaction_id&gt;</h6>
	</div>
</div>

```shell
curl -X POST https://example.com/wp-json/wc/v3/payments/reports/transactions/txn_1234567890abcdef \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
  "date": "2023-10-01 15:00:00",
  "transaction_id": "txn_1234567890abcdef",
  "payment_id": "pi_1234567890abcdef",
  "channel": "online",
  "payment_method": {
    "type": "card",
    "id": "pm_1234567890abcdef"
  },
  "type": "charge",
  "transaction_currency": "USD",
  "amount": 1000,
  "exchange_rate": 1.00,
  "deposit_currency": "USD",
  "fees": 20,
  "customer": {
    "name": "John Doe",
    "email": "john.doe@example.com",
    "country": "US"
  },
  "net_amount": 980,
  "order_id": 12345,
  "risk_level": 1,
  "deposit_date": "2023-10-02 15:00:00",
  "deposit_id": "dp_1234567890abcdef",
  "deposit_status": "completed"
}
```
## List authorizations

_@since 6.5.0_

Fetch a detailed overview of authorizations.
### Error codes

-   `rest_forbidden` - Unauthenticated
-   `rest_invalid_param` - Invalid request parameter
-   `wcpay_server_error` - Unexpected server error

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/reports/authorizations</h6>
	</div>
</div>

### Optional parameters

- `order_id`: Filter authorizations based on the associated order ID.
- `deposit_id`: Filter authorizations based on the associated deposit ID.
- `customer_email`: Filter authorizations based on the customer email.
- `payment_method_type`: Filter authorizations based on the payment method used.
- `type`: Filter authorizations where type is a specific value.
- `match`: Match filter for the authorizations.
- `user_timezone`: Include timezone into date filtering for authorizations.
- `page`: Page number for listing authorizations.
- `per_page`: Page size for listing authorizations.
- `sort`: Field on which to sort the authorizations.
- `direction`: Direction on which to sort the authorizations.

```shell
curl -X POST https://example.com/wp-json/wc/v3/payments/reports/authorizations?page=1 \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
[
  {
    "authorization_id":"auth_98765",
    "date":"2023-09-30 15:05:00",
    "payment_id":"pi_12345",
    "channel":"online",
    "payment_method":{
      "type":"card",
      "id":"pm_abc12345"
    },
    "currency":"USD",
    "amount":1000,
    "amount_captured":0,
    "fees":20,
    "customer":{
      "name":"John Doe",
      "email":"john.doe@example.com",
      "country":"US"
    },
    "net_amount":980,
    "order_id":45678,
    "risk_level":1
  }
]
```
## List authorization

_@since 6.5.0_

Fetch a detailed overview of single authorization.
### Error codes

-   `rest_forbidden` - Unauthenticated
-   `wcpay_server_error` - Unexpected server error

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/reports/authorizations/&lt;authorization_id&gt;</h6>
	</div>
</div>

```shell
curl -X POST https://example.com/wp-json/wc/v3/payments/reports/authorizations/auth_98765 \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
  "authorization_id":"auth_98765",
  "date":"2023-09-30 15:05:00",
  "payment_id":"pi_12345",
  "channel":"online",
  "payment_method":{
    "type":"card",
    "id":"pm_abc12345"
  },
  "currency":"USD",
  "amount":1000,
  "amount_captured":0,
  "fees":20,
  "customer":{
    "name":"John Doe",
    "email":"john.doe@example.com",
    "country":"US"
  },
  "net_amount":980,
  "order_id":45678,
  "risk_level":1
}
```
