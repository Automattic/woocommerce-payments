<?php

namespace WCPay\Core\DataTransferObjects;

use ReflectionClass;
use ReflectionException;

abstract class Data_Transfer_Object
{
	/**
	 * Return array of all properties.
	 *
	 * @return array
	 */
	public function to_array() {
		$class = new ReflectionClass($this);
		$data = [];
		foreach ($class->getProperties() as $reflectionProperty) {
			$data[$reflectionProperty->getName()] = $reflectionProperty->getValue();
		}

		return $data;
	}

	/**
	 * Change DTO property value if property is not private.
	 * @param ...$values
	 * @return self Cloned object with changed properties.
	 * @throws ReflectionException
	 */
	public function with(...$values)
	{
		$class = new ReflectionClass($this);
		$clone = $class->newInstanceWithoutConstructor();
		foreach ($class->getProperties() as $reflectionProperty) {
			$propertyValue = $reflectionProperty->getValue();
			$propertyName = $reflectionProperty->getName();
			if ( /*!$reflectionProperty->isPrivate() && */array_key_exists($propertyName, $values)) {
				$propertyValue = $values[$propertyName];
			}
			$clone->$propertyName = $propertyValue;
		}

		return $clone;

	}
}
