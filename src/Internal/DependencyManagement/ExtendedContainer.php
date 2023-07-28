<?php
/**
 * Class ExtendedContainer
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement;

use WooPayments\Vendor\League\Container\Container;

/**
 * Extends the League container to allow WooPayments customizations.
 *
 * During tests, `wcpay_get_test_container()` will return an instance of this class.
 */
class ExtendedContainer extends Container {
	/**
	 * Simple placeholder method, tbd.
	 *
	 * @param string $class_name  The name of the class.
	 * @param mixed  $replacement The class to replace it with.
	 */
	public function replace( $class_name, $replacement ) {

	}
}
