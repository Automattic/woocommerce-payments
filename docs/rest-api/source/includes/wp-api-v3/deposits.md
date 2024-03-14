# Deposits

The Deposits API endpoints provide access to an account's deposits data, including an overview of account balances, deposit schedule and deposit history.

## Deposit object

```json
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
}
```

### Properties

-   `id` _string_ - The deposit ID.
-   `date` _int_ - The arrival date of the deposit in unix timestamp milliseconds.
-   `type` _string_ - The type of deposit. `deposit` `withdrawal`
-   `amount` _int_ - The amount of the deposit.
-   `status` _string_ - The status of the deposit. `paid` `pending` `in_transit` `canceled` `failed`
-   `bankAccount` _string_ - The bank account the deposit was/will be paid to.
-   `currency` _string_ - The currency of the deposit. E.g. `eur`
-   `automatic` _bool_ - Returns `true` if the payout is created by an automated schedule and `false` if it’s requested manually.
-   `fee` _int_ - The fee amount of the deposit.
-   `fee_percentage` _int_ - The fee percentage of the deposit.
-   `created` _int_ - The arrival date of the deposit in unix timestamp seconds.

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
    -   `last_paid` _array_ of [**Deposit**](#deposit-object) - The last deposit that has been paid for each deposit currency.
    -   `last_manual_deposits` _array_ of [**Deposit**](#deposit-object) - Manual deposits that have been paid in the last 24 hours.
-   `balance` _object_
    -   `pending` _array_ - The pending balance for each deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `source_types` _object_ | _null_ - The amount of the balance from each source type, e.g. `{ "card": 12345 }`
    -   `available` _array_ - The available balance for each deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `source_types` _object_ | _null_ - The amount of the balance from each source type, e.g. `{ "card": 12345 }`
    -   `instant` _array_ - The instant balance for each deposit currency.
        -   `amount` _int_ - The amount of the balance.
        -   `currency` _string_ - The currency of the balance. E.g. `usd`.
        -   `fee` _int_ - The fee amount of the balance.
        -   `fee_percentage` _int_ - The fee percentage of the balance.
        -   `net` _int_ - The net amount of the balance.
-   `account` _object_
    -   `deposits_enabled` _bool_ - Whether deposits are enabled for the account.
    -   `deposits_blocked` _bool_ - Whether deposits are blocked for the account.
    -   `deposits_schedule` _object_
        -   `delay_days` _int_ - The number of days after a charge is created that the payment is paid out.
        -   `interval` _string_ - The interval at which payments are paid out. `manual` `daily` `weekly` `monthly`
        -   `weekly_anchor` _string_ | _undefined_ - The day of the week that payments are paid out, e.g. `monday`.
        -   `monthly_anchor` _int_ | _undefined_ - The day of the month that payments are paid out. Specified as a number between 1–31. 29-31 will instead use the last day of a shorter month.
    -   `default_currency` _string_ - The default currency for the account.
    -   `default_external_accounts` _array_ - The default external payout accounts (deposit destinations) for the account.
        -   `currency` _string_ - The currency of the external account. e.g. `eur`, `chf`.
        -   `status` _string_ - The status of the external account. e.g. `new`, `errored`.

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
		"last_manual_deposits": []
	},
	"balance": {
		"pending": [
			{
				"amount": -114696,
				"currency": "eur",
				"source_types": {
					"card": -114696
				}
			},
			{
				"amount": 707676,
				"currency": "usd",
				"source_types": {
					"card": 707676
				}
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
				"net": 0
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
		"default_currency": "eur",
		"default_external_accounts": [
			{
				"currency": "eur",
				"status": "new"
			},
			{
				"currency": "usd",
				"status": "new"
			}
		]
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
-   `status_is` _string_ `paid` `pending` `in_transit` `canceled` `failed`
-   `status_is_not` _string_ `paid` `pending` `in_transit` `canceled` `failed`
-   `direction` _string_
-   `page` _integer_
-   `pagesize` _integer_

### Returns

-   `data` _array_ of [**Deposit**](#deposit-object) - The list of deposits matching the query.
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
	"total_count": 2
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
-   `status_is` _string_ - `paid` `pending` `in_transit` `canceled` `failed`
-   `status_is_not` _string_ - `paid` `pending` `in_transit` `canceled` `failed`

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

If a deposit is found for the provided ID, the response will return a [**Deposit**](#deposit-object) object.

If no deposit is found for the provided ID, the response will return a `500` status code.

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
-   `currency`: _string_ - The currency of the balance to deposit. E.g. `usd`

```shell
curl -X POST 'https://example.com/wp-json/wc/v3/payments/deposits' \
  -u consumer_key:consumer_secret
  --data '{
      "type": "instant",
      "currency": "usd"
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
-   `status_is` _string_ - `paid` `pending` `in_transit` `canceled` `failed`
-   `status_is_not` _string_ - `paid` `pending` `in_transit` `canceled` `failed`

### Returns

-   `exported_deposits` _int_ - The number of deposits exported.

```shell
curl -X POST 'https://example.com/wp-json/wc/v3/payments/deposits/download?status_is=paid' \
  -u consumer_key:consumer_secret
  --data '{
      "user_email": "name@example.woo.com"
    }'
```

> JSON response example:

```json
{
	"exported_deposits": 42
}
```
