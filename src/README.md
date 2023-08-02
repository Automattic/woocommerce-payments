# WooPayments `src` directory

`src` is the directory, containing newer WooPayments code, and its structure.

The files in here:
- contain name-spaced classes from the `WCPay` namespace.
- follow the PSR-4 standard.
- are auto-loaded.
- use [the container](#dependency-container) for managing dependencies.

Please note that this is not the only code directory in WooPayments. Legacy code still lives within `includes`, and is maintained there. New code should only be developed within `src`.

## Dependency Container

`WCPay\Container` ([`Container.php`](Container.php)) is a [PSR-11](https://www.php-fig.org/psr/psr-11/) container, which is available both within and outside of `src`.

### Using the container

Most classes simply need to declare their dependencies in their constructor:

```php
namespace WCPay\Internal\Service;

class MyService {
	public function __construct( PaymentProcessingService $payment_processing_service ) {
		$this->payment_processing_service = $payment_processing_service;
	}
}
```

### Using the container as a dependency

It is recommended to use the constructor for injecting dependencies, however it is possible to also use the container as a dependency of the class, and retrieve instances later.

__Note:__ This approach is only suitable for classes, which are _not_ shared (ex. payment states). Shared classes are pratically singletons, as they will be resolved by the container only once. If you need to use this approach for shared classes, you are probably doing something wrong.

```php
namespace WCPay\Internal\Service;
use WCPay\Container;
use WCPay\Internal\Payment\State\Completed_State;

class MyService {
	private $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	public function my_method() {
		$state = $this->container->get( Completed_State::class );
	}
}
```

### Using the container within `includes`

`wcpay_get_container(): WCPay\Container` allows files in `includes` to load dependencies from within `src`. The function should never be used within `src`.

Use the `wcpay_get_container()->get( $id )` to load dependencies. This can be done:

- Within constructors whenever possible.
- Within a separate private method, which has a proper type hint. Otherwise the result of `wcpay_get_container()->get()` is `mixed`, which will prevent IDEs from understanding it correctly.

```php
use WCPay\Internal\Service\PaymentProcessingService;

class MyClass {
	/**
	 * @var PaymentProcessingService
	 */
	private $payment_processing_service;

	public function __construct() {
		$container = wcpay_get_container();
		$this->payment_processing_service = $container->get( PaymentProcessingService::class );
	}

	// or...

	/**
	 * Returns the dependency with a proper type hint.
	 * @return PaymentProcessingService
	 */
	private function get_payment_processing_service() {
		$container = wcpay_get_container();
		return $container->get( PaymentProcessingService::class );
	}
}
```

### Registering classes

We use [service providers](Internal/DependencyManagement/AbstractServiceProvider.php) to register our classes with the container. This is done manually in order to avoid having to use [reflection](https://www.php.net/manual/en/book.reflection.php), which may have a performance hit on merchant sites.

All service providers should reside within the `WCPay\Internal\DependencyManagement\ServiceProvider` namespace. Depending on the class you are registering, you can either use an existing service, or create another one for the topic of the class.

Here is the anatomy of a service:

```php
namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
// None of those exists, they are just examples.
use WC_Payments_API_Client;
use WCPay\Internal\Service\SimpleService;
use WCPay\Internal\Service\ServiceWithDependencies;
use WCPay\Internal\Request;

class PaymentsServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 * @var string[]
	 */
	protected $provides = [
		SimpleService::class,
		SimpleServiceWithDependencies::class,
		Request::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( SimpleService::class );

		$container->addShared( SimpleServiceWithDependencies::class )
			->addArgument( SimpleService::class);

		$container->add( Request::class )
			->addArgument( WC_Payments_API_Client::class );
	}
}
```

Highlights:

- The `$provides` property of the provider should contain the names of all provided classes. If the class is not defined there, `register` might never be called.
- `register()` should contain all registrations.
- `addShared()` is used to register shared classes. This is similar to singletons, meaning that only one instance will be ever created.
- `add()` registers a class, which will be instantiated every time it gets resolved.
- Both `add()` and `addShared()` return a definition, which can be further used to add the necessary constructor arguments through `addArgument()`. `addArgument()` returns the definition, so multiple calls can be chained.

### Loading Woo classes

A [delegate container](Internal/DependencyManagement/DelegateContainer/WooContainer.php) allows core Woo classes to be loaded through the WooPayments container.

The delegate container allows every class, available in the core container (`wc_get_container()`) is also available to be used as a dependency both within constructors, and through `wcpay_get_container()`.

### Unit tests

Pure unit tests should be able to create an instance of the service under test while providing mocks as its dependencies, and never use dependency injection. However, if required, the container allows dependencies to be replaced during tests.

While tests are running, the `wcpay_get_test_container()` function will return the container's internal [`ExtendedContainer`](Internal/DependencyManagement/ExtendedContainer.php) instance, which provides various methods, useful for testing, including:

- `replace( string $id, object $concrete )` allows providing either a different class name, or an instance for `$concrete`. The container will use it until reset.
- `reset_replacement( string $id )` resets a specific replacement.
- `reset_all_replacements()` resets all replacements that are in place.

```php
namespace WCPay\Tests;

use WCPay\Internal\Service\PaymentProcessingService;

class ContainerTest extends \WCPAY_UnitTestCase {
	public function test_something_with_dynamic_dependencies() {
		$mock_obj = $this->createMock( PaymentProcessingService::class );
		wcpay_get_test_container()->replace( PaymentProcessingService::class, $mock_obj );

		// Setup the mock, and run tests...

		// Reset either the particular replacement, or all.
		wcpay_get_test_container()->reset_replacement( PaymentProcessingService::class );
		wcpay_get_test_container()->reset_all_replacements();
	}	
}
```

Also, tests for `src` should be placed within the `tests/unit/src` directory, and use namespaces like in the example.
