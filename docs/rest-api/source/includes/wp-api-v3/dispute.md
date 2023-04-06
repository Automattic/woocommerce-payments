# Dispute

A dispute occurs when a customer contests a payment made with their card. 

Disputes should be responded to with evidence that the payment was legitimate.

The disputes API allows you to get data related to disputes such as a listing, summary or details of a particular dispute.

## Dispute Properties

| Attribute | Type    | Description                                        |
|-----------|---------|----------------------------------------------------|
| `dispute_id` | string | Unique identifier of the dispute.	|
| `charge_id` | string  | Unique identifier of the charge |
| `amount` | integer  | Amount of the dispute. |
| `currency` | string  | Currency of the dispute. |
| `reason` | string | Reason for the dispute. |
| `status` | string | Status of the dispute. Possible values are `warning_needs_response` , `warning_under_review` , `warning_closed` , `needs_response` , `under_review` , `charge_refunded` , `won` , or `lost` . |
| `balance_transactions` | array  | Chargeback transactions associated with this dispute. |
| `evidence_details` | object | Evidence details of the dispute. See [Evidence Details Properties](#evidence-details-properties) |
| `evidence` | object | Evidence provided to respond to a dispute. |
| `source` | string | Indicates the source which the payment was made from e.g. Card brand. |
| `customer_name` | string | Full name of the customer on Billing Details. |
| `customer_email` | string | Email id of the customer on Billing Details. |
| `customer_country` | string | Country of the customer on Billing Details. |
| `order` | object | Details of order associated with the dispute. See [Order Properties](#order-properties)  |
| `created` | string  | Date on which dispute raised. |

## Evidence Details Properties

| Attribute | Type    | Description                                        |
|-----------|---------|----------------------------------------------------|
| `due_by` | string | Date by which evidence must be submitted in order to successfully challenge dispute.	|
| `has_evidence` | string  | Whether evidence has been added to the dispute. |
| `past_due` | string  | Whether the last evidence submission was submitted after due date. |
| `submission_count` | array  | The number of times evidence has been submitted. Possible values 0 or 1. |

## Get disputes

Return the disputes.

### GET params

*   page: int
*   pagesize: int
*   sort: string
*   direction: string
*   search: string
*   match: string
* 	status_is: string
* 	status_is_not: string
*   currency_is: string
*   created_after: string
*   created_before: string
*   created_between: string

### Error codes

*   `wcpay_bad_request` - One or more query string params is invalid.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/disputes?page=1&pagesize=25&sort=created&direction=desc&search[0]=needs_response&search[1]=warning_needs_response</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/disputes?page=1&pagesize=25&sort=created&direction=desc&search[0]=needs_response&search[1]=warning_needs_response \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
  "data": [
        {
            "dispute_id": "dp_123",
            "charge_id": "ch_345",
            "amount": 2000,
            "currency": "usd",
            "reason": "product_not_received",
            "source": "visa",
            "order_number": 123,
            "customer_name": "Mr. Albert",
            "customer_email": "mr.albert@email.com",
            "customer_country": "US",
            "status": "needs_response",
            "created": "2023-04-06 04:19:38",
            "due_by": "2023-04-15 23:59:59",
            "order": {
                "number": "123",
                "url": "http:\/\/example.com\/wp-admin\/post.php?post=123&action=edit",
                "customer_url": "admin.php?page=wc-admin&path=\/customers&filter=single_customer&customers=1",
                "subscriptions": []
            }
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

## Get dispute

Return details of a particular dispute.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

   <i class="label label-get">GET</i>
   <h6>/wp-json/wc/v3/payments/disputes/&lt; dispute_id&gt; </h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/disputes/dp_123 \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
    "id": "dp_123",
    "object": "dispute",
    "amount": 2000,
    "balance_transaction": "txn_456",
    "balance_transactions": [
        {
            "id": "txn_456",
            "object": "balance_transaction",
            "amount": -2000,
            "available_on": 1681344000,
            "created": 1680754778,
            "currency": "usd",
            "description": "Chargeback withdrawal for ch_3MtkICFq1WsXC8Dk17mmZXIA",
            "exchange_rate": null,
            "fee": 1500,
            "fee_details": [
                {
                    "amount": 1500,
                    "application": null,
                    "currency": "usd",
                    "description": "Dispute fee",
                    "type": "stripe_fee"
                }
            ],
            "net": -3500,
            "reporting_category": "dispute",
            "source": "dp_1MtkIQFq1WsXC8Dk5NSbqpjt",
            "status": "pending",
            "type": "adjustment"
        }
    ],
    "created": 1680754778,
    "currency": "usd",
    "evidence": {
        "access_activity_log": null,
        "billing_address": "60 29th street suite\n-\nSan Francisco, CA, 94110, US",
        "cancellation_policy": null,
        "cancellation_policy_disclosure": null,
        "cancellation_rebuttal": null,
        "customer_communication": null,
        "customer_email_address": "test@woocommerce.com",
        "customer_name": "Test woocommerce",
        "customer_purchase_ip": null,
        "customer_signature": null,
        "duplicate_charge_documentation": null,
        "duplicate_charge_explanation": null,
        "duplicate_charge_id": null,
        "product_description": null,
        "receipt": null,
        "refund_policy": null,
        "refund_policy_disclosure": null,
        "refund_refusal_explanation": null,
        "service_date": null,
        "service_documentation": null,
        "shipping_address": null,
        "shipping_carrier": null,
        "shipping_date": null,
        "shipping_documentation": null,
        "shipping_tracking_number": null,
        "uncategorized_file": null,
        "uncategorized_text": null
    },
    "evidence_details": {
        "due_by": 1681603199,
        "has_evidence": false,
        "past_due": false,
        "submission_count": 0
    },
    "is_charge_refundable": false,
    "issuer_evidence": null,
    "livemode": false,
    "metadata": {},
    "payment_intent": "pi_3MtkICFq1WsXC8Dk1OyCBVb7",
    "reason": "product_not_received",
    "status": "needs_response",
    "order": {
        "number": "123",
        "url": "http:\/\/example.com\/wp-admin\/post.php?post=123&action=edit",
        "customer_url": "admin.php?page=wc-admin&path=\/customers&filter=single_customer&customers=1",
        "subscriptions": []
    },
}
```

## Get dispute summary

Returns summary of deposits

### GET params

*   search: string
*   match: string
* 	status_is: string
* 	status_is_not: string
*   currency_is: string
*   created_after: string
*   created_before: string
*   created_between: string

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

   <i class="label label-get">GET</i>
   <h6>/wp-json/wc/v3/payments/disputes/summary</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/disputes/summary \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
    "count": 1,
    "currencies": [
        "usd",
        "eur"
    ]
}
```
