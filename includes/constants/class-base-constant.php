<?php
/**
 * Class Base_Constant
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Base constant class to hold common logic for all constants.
 */
abstract class Base_Constant implements \JsonSerializable {

	/**
	 * Enum value
	 *
	 * @var mixed
	 */
	protected $value;

	/**
	 * Class constructor.
	 *
	 * @param mixed $value Constant from class.
	 * @throws \InvalidArgumentException
	 */
	public function __construct( $value ) {
		if ( $value instanceof static ) {
			$value = $value->get_value();
		} else {
			if ( ! defined( static::class . "::$value" ) ) {
				throw new \InvalidArgumentException( "Constant with name '$value' does not exist." );
			}
		}

		$this->value = $value;
	}

	/**
	 * Get enum class value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Compare to enums.
	 *
	 * @param mixed $variable Constant object to compare.
	 *
	 * @return bool
	 */
	final public function equals( $variable = null ): bool {
		return $variable instanceof Base_Constant && $this->get_value() === $variable->get_value() && static::class === \get_class( $variable );
	}

	/**
	 * Find constant in class by value.
	 *
	 * @param string $value Value to find.
	 *
	 * @return int|string
	 * @throws \InvalidArgumentException
	 */
	public static function search( string $value ) {
		$class = new ReflectionClass( static::class );
		$key   = array_search( $value, $class->getConstants(), true );
		if ( false === $key ) {
			throw new \InvalidArgumentException( "Constant with value '$value' does not exist." );
		}

		return $key;
	}

	/**
	 * Used to created enum from constant names like CLASS::ConstantName().
	 *
	 * @param string $name Name of property or function.
	 * @param array  $arguments Arguments of static call.
	 *
	 * @return static
	 * @throws \InvalidArgumentException
	 */
	public static function __callStatic( $name, $arguments ) {
		return new static( $name );
	}

	/**
	 * Get real enum value.
	 *
	 * @return mixed|string
	 */
	public function __toString() {
		return constant( \get_class( $this ) . '::' . $this->get_value() );
	}

	/**
	 * Specify the value which should be serialized to JSON.
	 *
	 * @return mixed|string
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->__toString();
	}
}
