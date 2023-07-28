<?php
/**
 * Class LegacyContainer
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement\Delegate;

use Psr\Container\ContainerInterface;
use WC_Payments;
use WCPay\Core\Mode;

/**
 * WooPayments Legacy Container Delegate.
 *
 * This class is a proxy between `src` and `includes`, allwing
 * `includes` classes to be used as dependencies.
 */
class LegacyContainer implements ContainerInterface {
	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed Entry.
	 */
	public function get( string $id ) {
		$method = $this->transform_class_to_method( $id );
		return $this->$method();
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool
	 */
	public function has( string $id ) {
		$method = $this->transform_class_to_method( $id );
		return method_exists( $this, $method );
	}

	/**
	 * Transforms the name of an existing class to a method name.
	 *
	 * @param string $class_name The name of a class from `includes`.
	 * @return string            Possibly the name of a private method of this class.
	 */
	private function transform_class_to_method( string $class_name ) {
		return 'get_' . str_replace( '\\', '_', strtolower( $class_name ) ) . '_instance';
	}

	/**
	 * Returns an instance of the mode class.
	 *
	 * @return Mode
	 */
	private function get_wcpay_core_mode_instance() {
		return WC_Payments::mode();
	}
}
