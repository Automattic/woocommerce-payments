# Deposits

The Deposits API endpoints provide access to an account's deposits data, including an overview of account balances, deposit schedule and deposit history.

## Get deposits overview for all account deposit currencies

Fetch an overview of account deposits for all deposit currencies. This includes details for the last paid deposit, next scheduled deposit, and last manual deposits.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/deposits/overview-all</h6>
	</div>
</div>

### Returns

-   `deposit` _object_
    -   `last_paid` _array_ - The last deposit that has been paid for each deposit currency.
    -   `next_scheduled` _array_ - The next scheduled deposit for each deposit currency.
    -   `last_manual_deposits` _array_ - Manual deposits that have been paid in the last 24 hours.
-   `balance` _object_
    -   `pending` _array_ - The pending balance for each deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `source_types` _object_ - The amount of the balance from each source type, e.g. `card` or `financing`.
        -   `deposits_count` _int_ - The number of deposits that make up the balance.
    -   `available` _array_ - The available balance for each deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `source_types` _object_ - The amount of the balance from each source type, e.g. `card` or `financing`.
    -   `instant` _array_ - The instant balance for each deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `fee` _int_ - The fee amount of the balance.
        -   `fee_percentage` _int_ - The fee percentage of the balance.
        -   `net` _int_ - The net amount of the balance.
        -   `transaction_ids` _array_ - The list of transaction IDs that make up the balance.
-   `account` _object_
    -   `deposits_enabled` _bool_ - Whether deposits are enabled for the account.
    -   `deposits_blocked` _bool_ - Whether deposits are blocked for the account.
    -   `deposits_schedule` _object_
        -   `delay_days` _int_ - The number of days after a charge is created that the payment is paid out.
        -   `interval` _string_ - The interval at which payments are paid out. `manual` `daily` `weekly` `monthly`
        -   `weekly_anchor` _string_ - The day of the week that payments are paid out, e.g. `monday`.
        -   `monthly_anchor` _int_ - The day of the month that payments are paid out. Specified as a number between 1–31. 29-31 will instead use the last day of a shorter month.
    -   `default_currency` _string_ - The default currency for the account.

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/overview-all \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
	"deposit": {
		"last_paid": [
			{
				"id": "po_1OJ466CBu6Jj8nBr38JRxdNE",
				"date": 1701648000000,
				"type": "deposit",
				"amount": 802872,
				"status": "paid",
				"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
				"currency": "eur",
				"automatic": true,
				"fee": 0,
				"fee_percentage": 0,
				"created": 1701648000
			},
			{
				"id": "po_1OHylNCBu6Jj8nBr95tE8scS",
				"date": 1701302400000,
				"type": "deposit",
				"amount": 471784,
				"status": "paid",
				"bankAccount": "STRIPE TEST BANK •••• 6789 (USD)",
				"currency": "usd",
				"automatic": true,
				"fee": 0,
				"fee_percentage": 0,
				"created": 1701302400
			}
		],
		"next_scheduled": [
			{
				"id": "wcpay_estimated_weekly_eur_1702598400",
				"date": 1702598400000,
				"type": "deposit",
				"amount": 458784,
				"status": "estimated",
				"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
				"currency": "eur",
				"automatic": true,
				"fee": 0,
				"fee_percentage": 0,
				"created": 1702598400
			},
			{
				"id": "wcpay_estimated_weekly_usd_1701993600",
				"date": 1701993600000,
				"type": "deposit",
				"amount": 823789,
				"status": "estimated",
				"bankAccount": "STRIPE TEST BANK •••• 6789 (USD)",
				"currency": "usd",
				"automatic": true,
				"fee": 0,
				"fee_percentage": 0,
				"created": 1701993600
			}
		],
		"last_manual_deposits": []
	},
	"balance": {
		"pending": [
			{
				"amount": -114696,
				"currency": "eur",
				"source_types": {
					"card": -114696
				},
				"deposits_count": 1
			},
			{
				"amount": 707676,
				"currency": "usd",
				"source_types": {
					"card": 707676
				},
				"deposits_count": 2
			}
		],
		"available": [
			{
				"amount": 573480,
				"currency": "eur",
				"source_types": {
					"card": 573480
				}
			},
			{
				"amount": 587897,
				"currency": "usd",
				"source_types": {
					"card": 587897
				}
			}
		],
		"instant": [
			{
				"amount": 12345,
				"currency": "usd",
				"fee": 185,
				"fee_percentage": 1.5,
				"net": 0,
				"transaction_ids": [
					"txn_3OHyIxCIHGKp1UAi0aVyDQ5D",
					"txn_3OJSuOCIHGKp1UAi1mRA2lL5"
				]
			}
		]
	},
	"account": {
		"deposits_enabled": true,
		"deposits_blocked": false,
		"deposits_schedule": {
			"delay_days": 7,
			"interval": "weekly",
			"weekly_anchor": "friday"
		},
		"default_currency": "eur"
	}
}
```

## Get deposits overview for single account deposit currency

Fetch an overview of account deposits for a single deposit currency. This includes details for the last paid deposit, next scheduled deposit, and last manual deposits.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/deposits/overview</h6>
	</div>
</div>

### Returns

-   `last_deposit` _object_|_null_- The last deposit that has been paid for the deposit currency.
-   `next_deposit` _object_|_null_ - The next scheduled deposit for the deposit currency.
-   `balance` _object_
    -   `pending` _object_ - The pending balance for the deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `source_types` _object_ - The amount of the balance from each source type, e.g. `card` or `financing`.
        -   `deposits_count` _int_ - The number of deposits that make up the balance.
    -   `available` _object_ - The available balance for the deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `source_types` _object_ - The amount of the balance from each source type, e.g. `card` or `financing`.
-   `instant_balance` _object_|_null_ - The instant balance for the deposit currency.
    -   `amount` _int_ - The amount of the balance.
    -   `currency` _string_ - The currency of the balance. E.g. `usd`.
    -   `fee` _int_ - The fee amount of the balance.
    -   `fee_percentage` _int_ - The fee percentage of the balance.
    -   `net` _int_ - The net amount of the balance.
    -   `transaction_ids` _array_ - The list of transaction IDs that make up the balance.
-   `account` _object_
    -   `deposits_disabled` _bool_ - Whether deposits are enabled for the account.
    -   `deposits_blocked` _bool_ - Whether deposits are blocked for the account.
    -   `deposits_schedule` _object_
        -   `delay_days` _int_ - The number of days after a charge is created that the payment is paid out.
        -   `interval` _string_ - The interval at which payments are paid out. `manual` `daily` `weekly` `monthly`
        -   `weekly_anchor` _string_ - The day of the week that payments are paid out, e.g. `monday`.
        -   `monthly_anchor` _int_ - The day of the month that payments are paid out. Specified as a number between 1–31. 29-31 will instead use the last day of a shorter month.
-   `default_currency` _string_ - The default currency for the account.

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/overview \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
	"last_deposit": {
		"id": "po_1OJ466CBu6Jj8nBr38JRxdNE",
		"date": 1701648000000,
		"type": "deposit",
		"amount": 802872,
		"status": "paid",
		"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
		"currency": "eur",
		"automatic": true,
		"fee": 0,
		"fee_percentage": 0,
		"created": 1701648000
	},
	"next_deposit": {
		"id": "wcpay_estimated_weekly_eur_1702598400",
		"date": 1702598400000,
		"type": "deposit",
		"amount": 458784,
		"status": "estimated",
		"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
		"currency": "eur",
		"automatic": true,
		"fee": 0,
		"fee_percentage": 0,
		"created": 1702598400
	},
	"balance": {
		"available": {
			"amount": 573480,
			"currency": "eur",
			"source_types": {
				"card": 573480
			}
		},
		"pending": {
			"amount": -114696,
			"currency": "eur",
			"source_types": {
				"card": -114696
			},
			"deposits_count": 1
		}
	},
	"instant_balance": {
		"amount": 0,
		"currency": "usd",
		"fee": 0,
		"fee_percentage": 1.5,
		"net": 0,
		"transaction_ids": []
	},
	"account": {
		"deposits_disabled": false,
		"deposits_blocked": false,
		"deposits_schedule": {
			"delay_days": 7,
			"interval": "weekly",
			"weekly_anchor": "friday"
		},
		"default_currency": "eur"
	}
}
```

## List deposits

Fetch a list of deposits.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/deposits</h6>
	</div>
</div>

### Required parameters

-   `sort` _string_ - Field on which to sort, e.g. `date`

### Optional parameters

-   `match` _string_
-   `store_currency_is` _string_
-   `date_before` _string_
-   `date_after` _string_
-   `date_between` _array_
-   `status_is` _string_ `paid` `pending` `in_transit` `canceled` `failed` `estimated`
-   `status_is_not` _string_ `paid` `pending` `in_transit` `canceled` `failed` `estimated`
-   `direction` _string_
-   `page` _integer_
-   `pagesize` _integer_

### Returns

-   `data` _array_ - List of deposit objects.
    -   `id` _string_ - The deposit ID.
    -   `date` _int_ - The date the deposit was paid in unix timestamp format.
    -   `type` _string_ - The type of deposit. `deposit` `withdrawal`
    -   `amount` _int_ - The amount of the deposit.
    -   `status` _string_ - The status of the deposit. `paid` `pending` `in_transit` `canceled` `failed` `estimated`
    -   `bankAccount` _string_ - The bank account the deposit was paid to.
    -   `currency` _string_ - The currency of the deposit. E.g. `eur`
    -   `automatic` _bool_ - Whether the deposit was paid automatically.
    -   `fee` _int_ - The fee amount of the deposit.
    -   `fee_percentage` _int_ - The fee percentage of the deposit.
    -   `created` _int_ - The date the deposit was created in unix timestamp format.
-   `total_count` _int_ - The total number of deposits matching the query.

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits?sort=date \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
	"data": [
		{
			"id": "wcpay_estimated_weekly_eur_1702598400",
			"date": 1702598400000,
			"type": "deposit",
			"amount": 458784,
			"status": "estimated",
			"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
			"currency": "eur",
			"automatic": true,
			"fee": 0,
			"fee_percentage": 0,
			"created": 1702598400
		},
		{
			"id": "po_1OJ466CBu6Jj8nBr38JRxdNE",
			"date": 1701648000000,
			"type": "deposit",
			"amount": 802872,
			"status": "paid",
			"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
			"currency": "eur",
			"automatic": true,
			"fee": 0,
			"fee_percentage": 0,
			"created": 1701648000
		},
		{
			"id": "po_1OHylNCBu6Jj8nBr95tE8scS",
			"date": 1701302400000,
			"type": "deposit",
			"amount": 471784,
			"status": "paid",
			"bankAccount": "STRIPE TEST BANK •••• 6789 (USD)",
			"currency": "usd",
			"automatic": true,
			"fee": 0,
			"fee_percentage": 0,
			"created": 1701302400
		}
	],
	"total_count": 3
}
```

## Get deposits summary

Fetches a summary of deposits matching the query. This includes the total number of deposits matching the query and a list of deposits.

Useful in combination with the **List deposits** endpoint to get a summary of deposits matching the query without having to fetch the full list of deposits.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/deposits/summary</h6>
	</div>
</div>

### Optional parameters

-   `match` _string_
-   `store_currency_is` _string_
-   `date_before` _string_
-   `date_after` _string_
-   `date_between` _array_
-   `status_is` _string_ - `paid` `pending` `in_transit` `canceled` `failed` `estimated`
-   `status_is_not` _string_ - `paid` `pending` `in_transit` `canceled` `failed` `estimated`

### Returns

-   `count` _int_ - The total number of deposits matching the query.
-   `store_currencies` _array_ - The currencies of the deposits matching the query.
-   `total` _int_ - The total amount of the deposits matching the query.
-   `currency` _string_ - The currency as provided by `store_currency_is` or the default currency of the account.

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/summary \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
	"count": 42,
	"store_currencies": [ "chf", "eur", "gbp", "nok", "sek", "usd", "dkk" ],
	"total": 5744395,
	"currency": "eur"
}
```

## Get deposit

Fetches a deposit by ID.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-get">GET</i>
		<h6>/wp-json/wc/v3/payments/deposits/{deposit_id}</h6>
	</div>
</div>

### Returns

-   `id` _string_ - The deposit ID.
-   `date` _int_ - The date the deposit was paid in unix timestamp format.
-   `type` _string_ - The type of deposit. `deposit` `withdrawal`
-   `amount` _int_ - The amount of the deposit.
-   `status` _string_ - The status of the deposit. `paid` `pending` `in_transit` `canceled` `failed` `estimated`
-   `bankAccount` _string_ - The bank account the deposit was paid to.
-   `currency` _string_ - The currency of the deposit. E.g. `eur`
-   `automatic` _bool_ - Whether the deposit was paid automatically.
-   `fee` _int_ - The fee amount of the deposit.
-   `fee_percentage` _int_ - The fee percentage of the deposit.
-   `created` _int_ - The date the deposit was created in unix timestamp format.

```shell
curl -X GET https://example.com/wp-json/wc/v3/payments/deposits/po_123abc \
	-u consumer_key:consumer_secret
```

> JSON response example:

```json
{
	"id": "po_1OGAFOCBu6Jj8nBruJbMbGqD",
	"date": 1701043200000,
	"type": "deposit",
	"amount": 114696,
	"status": "paid",
	"bankAccount": "STRIPE TEST BANK •••• 3000 (EUR)",
	"currency": "eur",
	"automatic": true,
	"fee": 0,
	"fee_percentage": 0,
	"created": 1701043200
}
```

## Submit an instant deposit

Submit an instant deposit for a list of transactions. Only for eligible accounts. See [Instant Deposits with WooPayments](https://woo.com/document/woopayments/deposits/instant-deposits/) for more information.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-post">POST</i>
		<h6>/wp-json/wc/v3/payments/deposits</h6>
	</div>
</div>

### Required body properties

-   `type`: _string_ - The type of deposit. `instant`
-   `transaction_ids`: _array_ - The list of transaction IDs to deposit.

```shell
curl -X POST 'https://example.com/wp-json/wc/v3/payments/deposits' \
  -u consumer_key:consumer_secret
  --data '{
      "type": "instant",
      "transaction_ids": [
          "txn_3OHyIxCIHGKp1UAi0aVyDQ5D",
          "txn_3OJSuOCIHGKp1UAi1mRA2lL5"
      ]
    }'
```

## Request a CSV export of deposits

Request a CSV export of deposits matching the query. A link to the exported CSV will be emailed to the provided email address or the account's primary email address if no email address is provided.

### HTTP request

<div class="api-endpoint">
	<div class="endpoint-data">
		<i class="label label-post">POST</i>
		<h6>/wp-json/wc/v3/payments/deposits/download</h6>
	</div>
</div>

### Optional body properties

-   `user_email`: _string_ - The email address to send the CSV export link to. If not provided, the account's primary email address will be used.

### Optional parameters

-   `match` _string_
-   `store_currency_is` _string_
-   `date_before` _string_
-   `date_after` _string_
-   `date_between` _array_
-   `status_is` _string_ - `paid` `pending` `in_transit` `canceled` `failed` `estimated`
-   `status_is_not` _string_ - `paid` `pending` `in_transit` `canceled` `failed` `estimated`

### Returns

-   `exported_deposits` _int_ - The number of deposits exported.

```shell
curl -X POST 'https://example.com/wp-json/wc/v3/payments/deposits/download?status_is=paid' \
  -u consumer_key:consumer_secret
  --data '{
      "user_email": "name@example.woo.com",
    }'
```

> JSON response example:

```json
{
	"exported_deposits": 42
}
```
