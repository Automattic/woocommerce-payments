<?php
/**
 * Class ExtendedContainer
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement;

use WCPay\Vendor\League\Container\Container;
use WCPay\Vendor\League\Container\Definition\Definition;
use WCPay\Vendor\League\Container\Exception\ContainerException;

/**
 * Extends the League container to allow WCPay customizations.
 *
 * During tests, `wcpay_get_test_container()` will return an instance of this class.
 */
class ExtendedContainer extends Container {
	/**
	 * Holds all replacements, which take precedence over definitions.
	 *
	 * @var Definition[]
	 */
	protected $replacements;

	/**
	 * Replaces an existing definition with another concret.
	 *
	 * Useful while testing, as the concrete could be a mock object.
	 *
	 * @param string $id       ID/Name of the class.
	 * @param object $concrete The concrete (instance) to use.
	 * @throws ContainerException In case the ID is not found within the container.
	 */
	public function replace( string $id, object $concrete ) {
		/**
		 * `has` checks existing definitions, providers, and delegate containers.
		 *
		 * If this returns false, then the ID cannot be resolved by the container.
		 */
		if ( ! $this->has( $id ) ) {
			throw new ContainerException(
				sprintf(
					'The ID you provided (%s) for replacement is not associated with anything inside the container or its delegates. Maybe try adding it instead?',
					$id
				)
			);
		}

		$this->replacements[ $id ] = new Definition( $id, $concrete );
	}

	/**
	 * Resets a specific replacement to the original definition.
	 *
	 * If the instance was never replaced, nothing will happen.
	 *
	 * @param string $id ID/name of the class.
	 */
	public function reset_replacement( string $id ) {
		if ( ! isset( $this->replacements[ $id ] ) ) {
			return;
		}

		unset( $this->replacements[ $id ] );
	}

	/**
	 * Resets all replacements.
	 */
	public function reset_all_replacements() {
		$this->replacements = [];
	}

	/**
	 * Resolves a definition.
	 *
	 * @param string $id ID/name of the class.
	 * @return mixed
	 */
	public function get( $id ) {
		if ( isset( $this->replacements[ $id ] ) ) {
			return $this->replacements[ $id ]->getConcrete();
		}

		return parent::get( $id );
	}
}
