# Deposit

A deposit represents a payout into the merchant bank account or debit card. 

Deposits can be past or future(estimated) and can have different status.

The deposit API allows you to get data related to deposits such as a listing, summary or details of a particular deposit.

## Transaction Properties

| Attribute | Type    | Description                                        |
|-----------|---------|----------------------------------------------------|
| `id` | string | Unique identifier of the deposit.	|
| `type` | string  | Type of deposit. Possible values include `deposit` or `withdrawal` |
| `status` | string  | Status of the deposit. Possible values are `paid` , `pending` , `in_transit` , `canceled` , `failed` or `estimated` . |
| `amount` | integer  | Amount of the deposit. |
| `currency` | string | Currency of the deposit. |
| `fee` | integer | Fee, if any, charged for the deposit. |
| `fee_percentage` | integer | Fee percentage on the deposit. |
| `automatic` | boolean  | Indicates that the deposit was automatic. `false` for manual deposits. |
| `bankAccount` | string  | Identifier of the bank account or card the deposit was made to. |
| `date` | string  | Timestamp indicating date of deposit, in milliseconds. |
| `created` | string  | Timestamp indicating date of deposit. |

## Get deposits

Return the deposits.

### GET params

*   page: int
*   pagesize: int
*   sort: string
*   direction: string
*   match: string
* 	status_is: string
* 	status_is_not: string
*   store_currency_is: string
*   date_before: string
*   date_after: string
*   date_between: string

### Error codes

*   `wcpay_bad_request` - One or more query string params is invalid.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

    <i class="label label-get">GET</i>
    <h6>/wp-json/wc/v3/payments/deposits?page=1&pagesize=25&sort=date&direction=desc</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits?page=1&pagesize=25&sort=date&direction=desc \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
  "data": [
      {
          "id": "wcpay_estimated_daily_usd_123",
          "date": 1681171200000,
          "type": "deposit",
          "amount": 1718,
          "status": "estimated",
          "bankAccount": "STRIPE TEST BANK 6789 (USD)",
          "currency": "usd",
          "automatic": true,
          "fee": 0,
          "fee_percentage": 0,
          "created": 1681171200
      }
  ],
  "total_count": 1
}
```

```json
{
	"code": "wcpay_bad_request",
	"message": "Error: Invalid sorting direction: ",
	"data": null
}
```

## Get deposit

Return data of a particular deposit.

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

   <i class="label label-get">GET</i>
   <h6>/wp-json/wc/v3/payments/deposits/&lt;deposit_id&gt; </h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/po_123 \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
    "id": "wcpay_estimated_daily_usd_123",
    "date": 1681171200000,
    "type": "deposit",
    "amount": 1718,
    "status": "estimated",
    "bankAccount": "STRIPE TEST BANK 6789 (USD)",
    "currency": "usd",
    "automatic": true,
    "fee": 0,
    "fee_percentage": 0,
    "created": 1681171200
}
```

## Get deposit summary

Returns summary of deposits

### GET params

*   match: string
* 	status_is: string
* 	status_is_not: string
*   store_currency_is: string
*   date_before: string
*   date_after: string
*   date_between: string

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

   <i class="label label-get">GET</i>
   <h6>/wp-json/wc/v3/payments/deposits/summary</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/summary \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
    "count": 1,
    "store_currencies": [
        "usd"
    ],
    "total": 1718,
    "currency": "usd"
}
```

## Get deposits overview

Returns an overview of deposits for the account

### HTTP request

<div class="api-endpoint">
  <div class="endpoint-data">

   <i class="label label-get">GET</i>
   <h6>/wp-json/wc/v3/payments/deposits/overview-all</h6>

  </div>
</div>

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/overview-all \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json"
```

> JSON response example:

```json
{
    "deposit": {
        "last_paid": [
          {
                "id": "po_123",
                "date": 1681171200000,
                "type": "deposit",
                "amount": 1350,
                "status": "paid",
                "bankAccount": "STRIPE TEST BANK 3000 (EUR)",
                "currency": "eur",
                "automatic": true,
                "fee": 0,
                "fee_percentage": 0,
                "created": 1681171200
            },
            {
                "id": "po_456",
                "date": 1681171200000,
                "type": "deposit",
                "amount": 1250,
                "status": "estimated",
                "bankAccount": "STRIPE TEST BANK 6789 (USD)",
                "currency": "usd",
                "automatic": true,
                "fee": 0,
                "fee_percentage": 0,
                "created": 1681171200
            }
        ],
        "next_scheduled": [
            {
                "id": "wcpay_estimated_daily_eur_1681257600",
                "date": 1681257600000,
                "type": "deposit",
                "amount": 1750,
                "status": "estimated",
                "bankAccount": "STRIPE TEST BANK 3000 (EUR)",
                "currency": "eur",
                "automatic": true,
                "fee": 0,
                "fee_percentage": 0,
                "created": 1681257600
            },
            {
                "id": "wcpay_estimated_daily_usd_1681257600",
                "date": 1681257600000,
                "type": "deposit",
                "amount": 1945,
                "status": "estimated",
                "bankAccount": "STRIPE TEST BANK 6789 (USD)",
                "currency": "usd",
                "automatic": true,
                "fee": 0,
                "fee_percentage": 0,
                "created": 1681257600
            }
        ],
        "last_manual_deposits": [
          {
					  "currency": "usd",
					  "date": "2023-04-03"
          },
          {
            "currency": "eur",
            "date": "2023-04-03"
          }
        ]
    },
    "balance": {
        "pending": [
            {
                "amount": 1945,
                "currency": "usd",
                "source_types": {
                    "card": 1945
                },
                "deposits_count": 1
            },
            {
                "amount": 1750,
                "currency": "eur",
                "source_types": {
                    "card": 1750
                },
                "deposits_count": 1
            }
        ],
        "available": [
            {
                "amount": 0,
                "currency": "usd",
                "source_types": {
                    "card": 0
                }
            },
            {
                "amount": 0,
                "currency": "eur",
                "source_types": {
                    "card": 0
                }
            }
        ],
        "instant": [
          {
                "amount": 1945,
                "currency": "usd",
                "automatic": true,
                "fee": 0,
                "fee_percentage": 0,
                "net": 1945,
                "transaction_ids": [ "txn_123", "txn_345"]
            },
            {
                "amount": 1750,
                "currency": "eur",
                "fee": 0,
                "fee_percentage": 0,
                "net": 1750,
                "transaction_ids": [ "txn_456"]
            },
        ]
    },
    "account": {
        "deposits_enabled": true,
        "deposits_blocked": false,
        "deposits_schedule": {
            "delay_days": 7,
            "interval": "daily"
        },
        "default_currency": "eur"
    }
}
```
