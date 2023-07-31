<?php
/**
 * Class Base_Constant_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Payment_Method;

/**
 * Base_Constant unit tests.
 */
class Base_Constant_Test extends WCPAY_UnitTestCase {

	public function test_base_constant_will_create_constant() {
		$class = Payment_Method::BASC();
		$this->assertInstanceOf( Payment_Method::class, $class );
		$this->assertSame( $class->get_value(), 'BASC' );
		$this->assertSame( (string) $class, 'bacs_debit' );
		$this->assertSame( wp_json_encode( $class ), '"bacs_debit"' );
	}
	public function test_base_constant_equals_function_will_return_true_if_classes_are_same_type_and_value() {
		$class_a = Payment_Method::BASC();
		$class_b = Payment_Method::BASC();
		$this->assertTrue( $class_a->equals( $class_b ) );
	}

	public function test_base_constant_equals_function_will_return_false_if_classes_are_not_same_type_or_value() {
		$class_a = Payment_Method::BASC();
		$class_b = Payment_Method::SEPA();
		$this->assertFalse( $class_a->equals( $class_b ) );
	}
	public function test_base_constant_equals_function_will_return_false_if_passed_argument_is_not_instance_of_base_class() {
		$class_a = Payment_Method::BASC();
		$class_b = new class() {

		};
		$this->assertFalse( $class_a->equals( $class_b ) );
	}

	public function test_exception_will_be_thrown_if_constant_not_exist_in_class() {
		$this->expectException( \InvalidArgumentException::class );
		Payment_Method::FOO();
	}

	public function test_class_will_return_const_name_if_searched_by_correct_const_value() {
		$name = Payment_Method::search( 'bacs_debit' );
		$this->assertSame( $name, 'BASC' );
	}

	public function test_class_will_throw_exception_if_searched_by_value_that_does_not_exist() {
		$this->expectException( \InvalidArgumentException::class );
		Payment_Method::search( 'foo' );
	}
}
