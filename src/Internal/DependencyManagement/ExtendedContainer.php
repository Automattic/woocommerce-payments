<?php
/**
 * Class ExtendedContainer
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement;

use WooPayments\Vendor\League\Container\Container;
use WooPayments\Vendor\League\Container\Definition\Definition;

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

		/**
		 * The `has` call above must have already triggered providers.
		 * If we have a definition, we'll store the original concrete.
		 *
		 * If the definitions don't contain the ID, it must be in a delegate container.
		 * In this case we'll store `null` in `original_concretes`, indicating that
		 * the definiton should be deleted when resetting replacements.
		 */
		if ( $this->definitions->has( $id ) ) {
			$definition = $this->extend( $id );

			// Store the original.
			$this->original_concretes[ $id ] = $definition->getConcrete();

			// Replace.
			$definition->setConcrete( $concrete );
		} else {
			// Create a new definition and store it.
			$definition = new Definition( $id, $concrete );
			$this->definitions->add( $id, $definition );

			// Store null to indicate that deletion is needed.
			$this->original_concretes[ $id ] = null;
		}
	}

	/**
	 * Resets a specific replacement to the original definition.
	 *
	 * If the instance was never replaced, nothing will happen.
	 *
	 * @param string $id ID/name of the class.
	 */
	public function reset_replacement( string $id ) {
		// Nothing to reset.
		if ( ! array_key_exists( $id, $this->original_concretes ) ) {
			return;
		}

		// Null concrete means delete instead of restore.
		if ( is_null( $this->original_concretes[ $id ] ) ) {
			// TBD: This should be replaced by another mechanism.
			$this->extend( $id )->setConcrete( $this->original_concretes[ $id ] );
		} else {
			$this->extend( $id )->setConcrete( $this->original_concretes[ $id ] );
		}

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
