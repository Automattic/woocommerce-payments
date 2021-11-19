<?php
/**
 * Class WCPay_Multi_Currency_Currency_Switcher_Widget_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;

/**
 * WCPay\MultiCurrency\CurrencySwitcherWidget unit tests.
 */
class WCPay_Multi_Currency_Currency_Switcher_Widget_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\MultiCurrency\Compatibility.
	 *
	 * @var WCPay\MultiCurrency\Compatibility|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_compatibility;

	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\MultiCurrency\CurrencySwitcherWidget instance.
	 *
	 * @var WCPay\MultiCurrency\CurrencySwitcherWidget
	 */
	private $currency_switcher_widget;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_compatibility  = $this->createMock( WCPay\MultiCurrency\Compatibility::class );
		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					new Currency( 'USD' ),
					new Currency( 'CAD', 1.2 ),
					new Currency( 'EUR', 0.8 ),
					new Currency( 'CHF', 1.1 ),
				]
			);

		$this->currency_switcher_widget = new WCPay\MultiCurrency\CurrencySwitcherWidget( $this->mock_multi_currency, $this->mock_compatibility );
	}

	public function test_widget_renders_title_with_args() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$instance = [
			'title' => 'Test Title',
		];
		$this->render_widget( $instance );
		$this->expectOutputRegex( '/<h2>Test Title<\/h2>/' );
		$this->expectOutputRegex( '/<section>/' );
		$this->expectOutputRegex( '/<\/section>/' );
		$this->expectOutputRegex( '/aria-label="Test Title"/' );
	}

	public function test_widget_renders_enabled_currencies_with_symbol() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$this->render_widget();
		$this->expectOutputRegex( '/<option value="USD">&#36; USD<\/option>/' );
		$this->expectOutputRegex( '/<option value="CAD">&#36; CAD<\/option>/' );
		$this->expectOutputRegex( '/<option value="EUR">&euro; EUR<\/option>/' );
		$this->expectOutputRegex( '/<option value="CHF">CHF<\/option>/' );
	}

	public function test_widget_renders_enabled_currencies_without_symbol() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$instance = [
			'symbol' => 0,
		];
		$this->render_widget( $instance );
		$this->expectOutputRegex( '/<option value="USD">USD<\/option>/' );
		$this->expectOutputRegex( '/<option value="CAD">CAD<\/option>/' );
		$this->expectOutputRegex( '/<option value="EUR">EUR<\/option>/' );
		$this->expectOutputRegex( '/<option value="CHF">CHF<\/option>/' );

	}

	public function test_widget_renders_enabled_currencies_with_symbol_and_flag() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$instance = [
			'symbol' => 1,
			'flag'   => 1,
		];
		$this->render_widget( $instance );
		$this->expectOutputRegex( '/<option value="USD">🇺🇸 &#36; USD<\/option>/' );
		$this->expectOutputRegex( '/<option value="CAD">🇨🇦 &#36; CAD<\/option>/' );
		$this->expectOutputRegex( '/<option value="EUR">🇪🇺 &euro; EUR<\/option>/' );
		$this->expectOutputRegex( '/<option value="CHF">🇨🇭 CHF<\/option>/' );

	}

	public function test_widget_renders_hidden_input() {
		$_GET = [
			'test_name'  => 'test_value',
			'test_array' => [ 0 => [ 0 => 'test_array_value' ] ],
			'named_key'  => [ 'key' => 'value' ],
		];
		$this->render_widget();
		$this->expectOutputRegex( '/<input type="hidden" name="test_name" value="test_value" \/>/' );
		$this->expectOutputRegex( '/<input type="hidden" name="test_array\[0\]\[0\]" value="test_array_value" \/>/' );
		$this->expectOutputRegex( '/<input type="hidden" name="named_key\[key\]" value="value" \/>/' );
	} //<input type="hidden" name="test_array[0][0]" value="test_array_value" />

	public function test_widget_selects_selected_currency() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( 'CAD' ) );
		$this->render_widget();
		$this->expectOutputRegex( '/<option value="USD">&#36; USD<\/option>/' );
		$this->expectOutputRegex( '/<option value="CAD" selected>&#36; CAD<\/option>/' );
		$this->expectOutputRegex( '/<option value="EUR">&euro; EUR<\/option>/' );
		$this->expectOutputRegex( '/<option value="CHF">CHF<\/option>/' );
	}

	public function test_widget_submits_form_on_change() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$this->render_widget();
		$this->expectOutputRegex( '/onchange="this.form.submit\(\)"/' );
	}

	public function test_widget_does_not_render_on_hide() {
		$this->mock_compatibility->method( 'should_hide_widgets' )->willReturn( true );
		$this->render_widget();
		$this->expectOutputString( '' );
	}

	public function test_widget_does_not_render_on_single_currency() {
		$mock_compatibility  = $this->createMock( WCPay\MultiCurrency\Compatibility::class );
		$mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );

		$mock_compatibility->method( 'should_hide_widgets' )->willReturn( false );
		$mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					new Currency( 'USD' ),
				]
			);

		$this->currency_switcher_widget = new WCPay\MultiCurrency\CurrencySwitcherWidget( $mock_multi_currency, $mock_compatibility );

		$this->render_widget();
		$this->expectOutputString( '' );
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
			'symbol' => 1,
			'flag'   => 0,
		];
		$this->currency_switcher_widget->form( $instance );
		$this->expectOutputRegex( '/name="widget-currency_switcher_widget\[\]\[title\]""/' );
		$this->expectOutputRegex( '/value="Custom title"/' );
		$this->expectOutputRegex( '/name="widget-currency_switcher_widget\[\]\[symbol\]".+checked/s' );
		$this->expectOutputRegex( '/name="widget-currency_switcher_widget\[\]\[flag\]"(?!.+checked).+\/>/s' );
	}

	/**
	 * Helper fuction to call widget method with default args.
	 *
	 * @param array $instance Saved values from database.
	 */
	private function render_widget( array $instance = [] ) {
		$this->currency_switcher_widget->widget(
			[
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
				'before_widget' => '<section>',
				'after_widget'  => '</section>',
			],
			$instance
		);
	}
}
