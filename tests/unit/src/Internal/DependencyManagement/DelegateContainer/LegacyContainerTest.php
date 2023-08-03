<?php
/**
 * Class LegacyContainerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\DependencyManagement\DelegateContainer;

use WCPAY_UnitTestCase;
use WCPay\Core\Mode;
use WCPay\Database_Cache;
use WCPay\Internal\DependencyManagement\DelegateContainer\LegacyContainer;

/**
 * Tests for the legacy container.
 */
class LegacyContainerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the container, which will be tested.
	 *
	 * @var Container
	 */
	private $sut;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->sut = new LegacyContainer();
	}

	/**
	 * Returns all classes, which should be supported by LegacyContainer.
	 */
	public function available_classes_provider() {
		return [
			[ \WCPay\Core\Mode::class ],
			[ \WC_Payment_Gateway_WCPay::class ],
			[ \WCPay\WooPay_Tracker::class ],
			[ \WCPay\WC_Payments_Checkout::class ],
			[ \WCPay\Database_Cache::class ],
			[ \WC_Payments_Account::class ],
			[ \WC_Payments_API_Client::class ],
			[ \WC_Payments_Localization_Service::class ],
			[ \WC_Payments_Action_Scheduler_Service::class ],
			[ \WC_Payments_Fraud_Service::class ],
			[ \WC_Payments_Customer_Service::class ],
		];
	}

	/**
	 * Makes sure that the container indicates that it has a certain class,
	 * and that it returns an instance of that very class.
	 *
	 * @param string $class_name The name of the needed class.
	 * @dataProvider available_classes_provider
	 */
	public function test_retrieval( $class_name ) {
		$this->assertTrue( $this->sut->has( $class_name ) );
		$this->assertInstanceOf( $class_name, $this->sut->get( $class_name ) );
	}
}
