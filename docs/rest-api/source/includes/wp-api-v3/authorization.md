# Authorization

An authorization is an uncaptured transaction marked for manual capture later, or more technically a payment intent with `requires_capture` status. An authorization must be captured within 7 days after which it expires and becomes unavailable for capture.

An authorization may be captured from one of these pages - Order details, Uncapture transactions, Payment details.

The authorization API allows you to get data related to authorizations.

## Authorization Properties

| Attribute | Type | Description |
| --------- | ------ | --------------- |
| `charge_id` | string | Unique identifier of the charge. |
| `transaction_id` | string | Unique identifier of the transaction associated with this charge. |
| `payment_intent_id` | string | Unique identifier of the payment intent associated with this charge. |
| `order_id` | integer | Unique identifier of the order associated with this charge. |
| `amount` | integer | Amount intended to be collected by this charge. |
| `amount_captured` | integer | Amount captured in cents. |
| `amount_refunded` | integer | Amount refunded in cents. |
| `net` | integer  | The Net amount of the authorization in cents. |
| `refunded` | boolean | Indicates whether the charge has been fully refunded. |
| `is_captured` | boolean | Indicates whether the charge has been captured. |
| `channel` | string | Channel through which the charge was made. Possible values are `online` or `in_person`. |
| `source` | string | Source of the charge. Value is the Card Brand or Payment Method Type. |
| `source_identifier` | string | Identifier for the source. In case of card, the value will be the last four digits of the card. |
| `customer_name` | string | Full name of the customer on Billing Details. |
| `customer_email` | string | Email id of the customer on Billing Details. |
| `customer_country` | string | Country of the customer on Billing Details. |
| `fees` | integer | The amount of the application fee for the charge. |
| `currency` | string | The ISO currency code of the charge. |
| `risk_level` | integer | An integer indicating the risk level associated with the transaction. Possible values are 0 (Normal), 1 (Elevated), 2 (Highest). |
| `outcome_type` | string | Indicates outcome of the payment. Possible values are `authorized` , `manual_review` , `issuer_declined` , `blocked` , or `invalid`. |
| `status` | string | Indicates status of the charge. Possible values are `succeeded` , `pending` , or `failed`. |
| `created` | string | The date the charge was created in UTC. |
| `modified` | string | The date the charge was modified in UTC. |

## List authorizations

_@since v4.9.0_

Return all active authorizations.

### GET params

*   page: int
*   pagesize: int
*   sort: string
*   direction: string

### Error codes

*   `wcpay_bad_request` - One or more query string params is invalid.

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

```Error json
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
