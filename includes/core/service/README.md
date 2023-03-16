# WooCommerce Payments Services

## Purpose

Expose WooCommerce Payments functionality through Services. Extensions should use these integration points provided in the `WCPay\Core` namespace, and should not use any methods from other classes, which are considered internal to WCPay. 

In this way, extensions will be able to use services in a reliable, and compatible manner. 

## Services available

- [`WC_Payments_Customer_Service_API`](customer-service.md)

## Adding New Services

- Add the new service within `includes\core\service`.
- Service should be in `WCPay\Core` namespace.
- Add functions in your service that act as proxies to the internal service methods, or consider how they might change in the future.
- Add tests in `tests\unit\core\service`.
- Expose service via `WC_Payments`.

[🔗 More about services within WooCommerce Payments Core](../CONTRIBUTING.md)
