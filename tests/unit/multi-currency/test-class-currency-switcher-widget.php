<?php
/**
 * Class WCPay_Multi_Currency_Currency_Switcher_Widget_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Multi_Currency\Currency;

/**
 * WCPay\Multi_Currency\Currency_Switcher_Widget unit tests.
 */
class WCPay_Multi_Currency_Currency_Switcher_Widget_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\Multi_Currency\Multi_Currency.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\Multi_Currency\Currency_Switcher_Widget instance.
	 *
	 * @var WCPay\Multi_Currency\Currency_Switcher_Widget
	 */
	private $currency_switcher_widget;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\Multi_Currency\Multi_Currency::class );
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					new Currency( 'USD' ),
					new Currency( 'CAD', 1.2 ),
					new Currency( 'EUR', 0.8 ),
				]
			);

		$this->currency_switcher_widget = new WCPay\Multi_Currency\Currency_Switcher_Widget( $this->mock_multi_currency );
	}

	public function test_widget_output() {
		$args = [
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section>',
			'after_widget'  => "</section>\n",
		];

		$instance = [
			'title' => 'Test Title',
		];

		ob_start();
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( 'CAD' ) );
		$this->currency_switcher_widget->widget( $args, $instance );
		$output = ob_get_clean();
		$this->assertContains( '<h2>Test Title</h2>', $output );
		$this->assertContains( '<section>', $output );
		$this->assertContains( '</section>', $output );
		$this->assertContains( 'aria-label="Test Title"', $output );
		$this->assertContains( 'onchange="this.form.submit()"', $output );
		$this->assertContains( '<option value="USD">&#36; USD</option>', $output );
		$this->assertContains( '<option value="CAD" selected>&#36; CAD</option>', $output );
		$this->assertContains( '<option value="EUR">&euro; EUR</option>', $output );

		// Show flag and hide symbol.
		ob_start();
		$instance['flag']   = true;
		$instance['symbol'] = false;
		$this->currency_switcher_widget->widget( $args, $instance );
		$output = ob_get_clean();
		$this->assertContains( '<option value="USD">🇺🇸 USD</option>', $output );
	}

	public function test_update_method() {
		$old_instance = [];
		$new_instance = [
			'title'  => "Title <br/> \n",
			'symbol' => 'on',
		];

		$result = $this->currency_switcher_widget->update( $new_instance, $old_instance );
		$this->assertEquals(
			[
				'title'  => 'Title',
				'symbol' => 1,
				'flag'   => 0,
			],
			$result
		);
	}

	public function test_form_output() {
		$instance = [
			'title'  => 'Custom title',
			'symbol' => 0,
			'flag'   => 0,
		];

		ob_start();
		$this->currency_switcher_widget->form( $instance );
		$output = ob_get_clean();

		$this->assertContains( 'name="widget-currency_switcher_widget[][title]"', $output );
		$this->assertContains( 'value="Custom title"', $output );
		$this->assertContains( 'name="widget-currency_switcher_widget[][symbol]"', $output );
		$this->assertContains( 'name="widget-currency_switcher_widget[][flag]"', $output );
		$this->assertNotContains( 'checked=\'checked\'', $output );
	}
}
