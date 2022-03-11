# Introduction

WooCommerce (WC) 2.6+ is fully integrated with the WordPress [REST](http://en.wikipedia.org/wiki/Representational_State_Transfer) API. This allows WC data to be created, read, updated, and deleted using requests in JSON format and using WordPress REST API Authentication methods and standard HTTP verbs which are understood by most HTTP clients.

The current WP REST API integration version is `v3` which takes a first-order position in endpoints.

## Requirements

To use the latest version of the REST API you must be using:

-   WooCommerce 3.5+.
-   WordPress 4.4+.
-   Pretty permalinks in `Settings > Permalinks` so that the custom endpoints are supported. **Default permalinks will not work.**
-   You may access the API over either HTTP or HTTPS, but _HTTPS is recommended where possible_.

If you use ModSecurity and see `501 Method Not Implemented` errors, see [this issue](https://github.com/woocommerce/woocommerce/issues/9838) for details.

<aside class="notice">
	Please note that you are <strong>not</strong> required to install the <a href="https://wordpress.org/plugins/rest-api/" target="_blank">WP REST API (WP API)</a> plugin.
</aside>

## Request/Response Format

The default response format is JSON. Requests with a message-body use plain JSON to set or update resource attributes. Successful requests will return a `200 OK` HTTP status.

Some general information about responses:

-   Dates are returned in ISO8601 format: `YYYY-MM-DDTHH:MM:SS`
-   Resource IDs are returned as integers.
-   Any decimal monetary amount, such as prices or totals, will be returned as strings with two decimal places.
-   Other amounts, such as item counts, are returned as integers.
-   Blank fields are generally included as `null` or emtpy string instead of being omitted.

## Errors

Occasionally you might encounter errors when accessing the REST API. There are four possible types:

| Error Code                  | Error Type                                                  |
| --------------------------- | ----------------------------------------------------------- |
| `400 Bad Request`           | Invalid request, e.g. using an unsupported HTTP method      |
| `401 Unauthorized`          | Authentication or permission error, e.g. incorrect API keys |
| `404 Not Found`             | Requests to resources that don't exist or are missing       |
| `500 Internal Server Error` | Server error                                                |

> WP REST API error example:

```json
{
	"code": "rest_no_route",
	"message": "No route was found matching the URL and request method",
	"data": {
		"status": 404
	}
}
```

> WooCommerce REST API error example:

```json
{
	"code": "woocommerce_rest_term_invalid",
	"message": "Resource doesn't exist.",
	"data": {
		"status": 404
	}
}
```

Errors return both an appropriate HTTP status code and response object which contains a `code`, `message` and `data` attribute.

### Tools

Some useful tools you can use to access the API include:

-   [Insomnia](https://insomnia.rest) - Cross-platform GraphQL and REST client, available for Mac, Windows, and Linux.
-   [Postman](https://www.getpostman.com/) - Cross-platform REST client, available for Mac, Windows, and Linux.
-   [RequestBin](https://requestbin.com) - Allows you test webhooks.
-   [Hookbin](https://hookbin.com/) - Another tool to test webhooks.

## Learn more

Learn more about the REST API checking the <a href="https://developer.wordpress.org/rest-api/">official WordPress REST API documentation</a>.
