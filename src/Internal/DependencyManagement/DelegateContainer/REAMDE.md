# WooPayments Delegate Containers

This directory contains two containers:

1. `WCPay\Internal\DependencyManagement\DelegateContainer\LegacyContainer`
2. `WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer`

Both of those are classes, which implement the PSR-11 container interface, and are provided as delegate containers to `WCPay\Container`. This means that whenever a class is requested, and the service providers within `WCPay\Internal\Dependencymanagement\ServiceProvider` do not provide the requested class, the main container will delegate the resolution to both of those containers, in the order above.

## `WooContainer`

`WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer` is a simple class, which delegates its `has` and `get` methods to the corresponding methods of the [Woo core container](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce/src#the-container), available through `wc_get_container()`.

🔗 Please read the [core docs](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce/src#the-container) for more information.

## `LegacyContainer`

PSR-11 containers require the `has( $class_name )` method to be checked first, before calling `get( $class_name )` to retrieve an instance. Whenever the main container tries to delegate to [`LegacyContainer`](LegacyContainer.php), it works as follows:

1. The class name is transformed to a method name.
    - It's all transformed to lowerspace.
    - Namespace delimiters (`\`) are transformed to underscores.
    - The resulting string is prepended with `get_` and appended with `_instance`.
2. `has()` checks whether there is a private method of the container with the given name. If yes, the execution proceeds with
3. `get()` which calls the method, and returns its result.

For example, if the full class name is `WCPay\Core\Mode`, the method name will be `get_wcpay_core_mode_instance`. Within the legacy container, the method looks like this:

```php
final class LegacyContainer implements ContainerInterface {
	private function get_wcpay_core_mode_instance() {
		return WC_Payments::mode();
	}
}
```

### Currently supported classes

This is a list of the currently supported legacy classes by the container.

- `WCPay\Core\Mode`
- `WC_Payment_Gateway_WCPay`
- `WCPay\WooPay_Tracker`
- `WCPay\WC_Payments_Checkout`
- `WCPay\Database_Cache`
- `WC_Payments_Account`
- `WC_Payments_API_Client`
- `WC_Payments_Localization_Service`
- `WC_Payments_Action_Scheduler_Service`
- `WC_Payments_Fraud_Service`
- `WC_Payments_Customer_Service`

### Adding classes

If you need support for another class, please add the necessary method.

⚠️ Don't forget to add another test to `WCPay\Tests\Internal\DependencyManagement\DelegateContainer\LegacyContainerTest`. 

This is the only way to make sure that changes within `includes` would not break dependencies within `src`.
