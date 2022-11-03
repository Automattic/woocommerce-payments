<?php
/**
 * Base DTO.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\DataTransferObjects;

use ReflectionClass;
use ReflectionException;

/**
 * Abstract DTO class.
 */
abstract class Data_Transfer_Object {

	/**
	 * Return array of all properties.
	 *
	 * @return array
	 */
	public function to_array() {
		$class = new ReflectionClass( $this );
		$data  = [];
		foreach ( $class->getProperties() as $reflectionProperty ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$data[ $reflectionProperty->getName() ] = $reflectionProperty->getValue(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		}

		return $data;
	}

	/**
	 * The purpose of this function is to DTOs data if passed array have a key where property exists in class . Since all DTOs are immutable, new DTO will be created with the data that was passed.
	 *
	 * @param mixed ...$values Values to change.
	 * @return self Cloned object with changed properties.
	 * @throws ReflectionException
	 */
	public function with( ...$values ) {
		$class = new ReflectionClass( $this );
		$clone = $class->newInstanceWithoutConstructor();
		foreach ( $class->getProperties() as $reflectionProperty ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$propertyValue = $reflectionProperty->getValue(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$propertyName  = $reflectionProperty->getName(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			if ( array_key_exists( $propertyName, $values ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
				$propertyValue = $values[ $propertyName ]; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			}
			$clone->$propertyName = $propertyValue; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		return $clone;

	}
}
