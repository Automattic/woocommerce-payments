# Authorization

An authorization is an uncaptured transaction marked for manual capture later, or more technically a payment intent with `requires_capture` status. An authorization must be captured within 7 days after which it expires and becomes unavailable for capture.

An authorization may be captured from one of these pages - Order details, Uncapture transactions, Payment details.

The authorization API allows you to get data related to authorizations.

## Get authorizations

_@since v4.9.0_

Return all active authorizations.

### GET params

-   page: int
-   pagesize: int
-   sort: string
-   direction: string

### Error codes

-   `wcpay_bad_request` - One or more query string params is invalid.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">
    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/authorizations?page=1&pagesize=10&sort=created&direction=DESC</h6>
  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/authorizations?page=1&pagesize=10&sort=created&direction=DESC \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
	"data": [
		{
			"charge_id": "ch_123",
			"transaction_id": null,
			"amount": 1800,
			"net": 1720,
			"amount_captured": 0,
			"amount_refunded": 0,
			"is_captured": false,
			"created": "2022-11-03 08:56:14",
			"modified": "2022-11-03 08:56:16",
			"channel": "online",
			"source": "visa",
			"source_identifier": "4242",
			"customer_name": "Mr. Albert",
			"customer_email": "mr.albert@email.com",
			"customer_country": "US",
			"fees": 80,
			"currency": "usd",
			"risk_level": 0,
			"payment_intent_id": "pi_123",
			"refunded": false,
			"order_id": 329,
			"outcome_type": "authorized",
			"status": "succeeded"
		}
	]
}
```

```json
{
	"code": "wcpay_bad_request",
	"message": "Error: Invalid sorting direction: ",
	"data": null
}
```

## Get authorization

_@since v4.9.0_

Return data for a specific authorization.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">
    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/authorizations/&lt;payment_intent_id&gt;</h6>
  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/authorizations/pi_123 \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
	"wcpay_charges_cache_id": 17,
	"stripe_account_id": "acct_123",
	"charge_id": "ch_123",
	"transaction_id": null,
	"amount": 1800,
	"net": 1720,
	"amount_captured": 0,
	"amount_refunded": 0,
	"is_captured": false,
	"created": "2022-11-03 08:56:14",
	"modified": "2022-11-03 08:56:16",
	"channel": "online",
	"source": "visa",
	"source_identifier": "4242",
	"customer_name": "Mr. Albert",
	"customer_email": "mr.albert@email.com",
	"customer_country": "US",
	"fees": 80,
	"currency": "usd",
	"risk_level": 0,
	"payment_intent_id": "pi_123",
	"refunded": false,
	"order_id": 329,
	"outcome_type": "authorized",
	"status": "succeeded"
}
```

## Get authorization summary

_@since v4.9.0_

Return a high-level summary for all active authorizations.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">
    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/authorizations/summary</h6>
  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/authorizations/summary \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
	"count": 3,
	"currency": "usd",
	"total": 5400,
	"all_currencies": [ "usd" ]
}
```
