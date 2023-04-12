# Transaction

A transaction represents a payment event on your WooCommerce Payments account. 

Transactions can be of different types such as Charge (indicating a successful charge on a card), Payment (indicating a charge on an alternative payment method), Refund (indicating a refund to a customer), Dispute (indicating a dispute from a customer) etc.

The transaction API allows you to get data related to transactions.

## Transaction Properties

| Attribute | Type    | Description                                        |
|-----------|---------|----------------------------------------------------|
| `transaction_id` | string | Unique identifier of the transaction.	|
| `type` | string  | Type of transaction. Possible values include `charge`, `payment`, `dispute`, `refund`, `refund_failure` etc. |
| `channel` | string  | Channel over which the transaction was processed. Possible values are `online` or `in_person` . |
| `source` | string  | Source of the transaction. Value is the Card Brand or Payment Method Type. |
| `source_identifier` | string  | Identifier for the source. In case of card, the value will be the last four digits of the card. |
| `customer_name` | string | Full name of the customer on Billing Details. |
| `customer_email` | string | Email id of the customer on Billing Details. |
| `customer_country` | string | Country of the customer on Billing Details. |
| `amount` | integer  | Amount of this transaction. |
| `fees` | integer  | The amount of the application fee for the transaction. |
| `net` | integer  | The Net amount of the transaction. |
| `currency` | string  | The currency code for the transaction. |
| `risk_level` | integer  | An integer indicating the risk level associated with the transaction. Possible values are 0 (Normal), 1 (Elevated), 2 (Highest). |
| `charge_id` | string  | Unique identifier of the charge. |
| `payment_intent_id` | string  | Unique identifier of the payment intent for the transaction. |
| `order_id` | string  | Unique identifier of the order associated with the transaction. |
| `order` | object  | Order associated with the transaction. See [Order Properties](#order-properties) . |
| `loan_id` | string  | Unique identifier of the loan, if any, associated with the transaction. |
| `deposit_id` | string  | Unique identifier of the deposit associated with the transaction. |
| `deposit_status` | string  | The status of the deposit. Possible values are `Estimated` , `Pending` , `In transit` , `Paid` , `Canceled` , `Failed` . |
| `available_on` | Date on which the transaction amount will be paid out to the merchant. |
| `customer_amount` | string  | The customer amount for the transaction. |
| `customer_currency` | string  |  The customer currency code for the transaction. |
| `amount_in_usd` | string  | The amount in USD for the transaction. |
| `source_device` | string  | Identifier for the device platform when a device such as a card reader is used for the transaction. |

## Order Properties

| Attribute | Type    | Description                                        |
|-----------|---------|----------------------------------------------------|
| `number` | string | Order identifier.	|
| `url` | string  | URL for the Order Edit screen for this order. |
| `customer_url` | string  | URL for the Customer screen on Admin dashboard. |
| `subscriptions` | array  | Array of Order objects for subscriptions associated with this order. |

## Get transactions

Return the transactions.

### GET params

*   page: int
*   pagesize: int
*   sort: string
*   direction: string
*   search: string
*   match: string
* 	customer_currency_is: string
* 	customer_currency_is_not: string
*   store_currency_is: string
*   deposit_id: string
*   loan_id_is: string
*   type_is: string
*   type_is_not: string

### Error codes

*   `wcpay_bad_request` - One or more query string params is invalid.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/transactions?page=1&pagesize=25&sort=date&direction=desc</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/transactions?page=1&pagesize=25&sort=date&direction=desc \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
	"data": [
		{
            "transaction_id": "txn_123",
            "type": "payment",
            "channel": "online",
            "date": "2023-04-03 09:07:29",
            "source": "visa",
            "source_identifier": "4242",
            "customer_name": "Mr. Albert",
            "customer_email": "mr.albert@email.com",
            "customer_country": "US",
            "amount": 2485,
            "net": 2359,
            "fees": 126,
            "currency": "usd",
            "risk_level": null,
            "charge_id": "py_1234",
            "deposit_id": "wcpay_estimated_daily_usd_1234",
            "available_on": "2023-04-05",
            "exchange_rate": 1.00000,
            "customer_amount": 2485,
            "customer_currency": "usd",
            "loan_id": null,
            "order_id": 123,
            "amount_in_usd": 2485,
            "source_device": null,
            "deposit_status": "estimated",
            "order": {
                "number": "123",
                "url": "http:\/\/example.com\/wp-admin\/post.php?post=123&action=edit",
                "customer_url": "admin.php?page=wc-admin&path=\/customers&filter=single_customer&customers=1",
                "subscriptions": []
            },
            "payment_intent_id": "pi_231"
        },
        {
            "transaction_id": "txn_345",
            "type": "refund",
            "channel": "online",
            "date": "2023-04-03 09:05:28",
            "source": "visa",
            "source_identifier": "3184",
            "customer_name": "Mr. Test",
            "customer_email": "mr.test@email.com",
            "customer_country": "US",
            "amount": -2485,
            "net": -2485,
            "fees": 0,
            "currency": "usd",
            "risk_level": 0,
            "charge_id": "ch_345",
            "deposit_id": "wcpay_estimated_daily_usd_567",
            "available_on": "2023-04-03",
            "exchange_rate": 1.00000,
            "customer_amount": 2485,
            "customer_currency": "usd",
            "loan_id": null,
            "order_id": 456,
            "amount_in_usd": -2485,
            "source_device": null,
            "deposit_status": "estimated",
            "order": {
                "number": "456",
                "url": "http:\/\/wcpay.test\/wp-admin\/post.php?post=456&action=edit",
                "customer_url": "admin.php?page=wc-admin&path=\/customers&filter=single_customer&customers=1",
                "subscriptions": []
            },
            "payment_intent_id": "pi_345"
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

## Get transaction summary

Return a high-level summary for all transactions.

### GET params

*   search: string
*   match: string
* 	date_before: string
* 	date_after: string
* 	date_between: string
* 	customer_currency_is: string
* 	customer_currency_is_not: string
*   store_currency_is: string
*   deposit_id: string
*   loan_id_is: string
*   type_is: string
*   type_is_not: string

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/transactions/summary</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/transactions/summary \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
	"count": 3,
	"currency": "usd",
	"total": 5400,
	"fees":208,
	"net":5192,
	"store_currencies":["usd"],
	"customer_currencies":["usd","eur"]
}
```
