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
	 * Concretes will be stored here before being replaced,
	 * allowing them to be restored later.
	 *
	 * @var array
	 */
	protected $original_concretes = [];

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
		if ( ! $this->has( $id ) ) {
			throw new ContainerException(
				sprintf(
					'The ID you provided (%s) for replacement is not associated with anything inside the container. Maybe try adding it instead?',
					$id
				)
			);
		}

		$definition = $this->extend( $id );

		// Store the original.
		$this->original_concretes[ $id ] = $definition->getConcrete();

		// Replace.
		$definition->setConcrete( $concrete );
	}

	/**
	 * Resets a specific replacement to the original definition.
	 *
	 * If the instance was never replaced, nothing will happen.
	 *
	 * @param string $id ID/name of the class.
	 */
	public function reset_replacement( string $id ) {
		if ( ! isset( $this->original_concretes[ $id ] ) ) {
			return;
		}

		$this->extend( $id )->setConcrete( $this->original_concretes[ $id ] );
		unset( $this->original_concretes[ $id ] ); // No longer needed.
	}

	/**
	 * Resets all replacements.
	 */
	public function reset_all_replacements() {
		foreach ( $this->original_concretes as $id => $concrete ) {
			$this->extend( $id )->setConcrete( $concrete );
		}

		$this->original_concretes = [];
	}
}
