<?php
/**
 * Class WCPay_Multi_Currency_Currency_Switcher_Block_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\MultiCurrency\Compatibility;
use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\CurrencySwitcherBlock;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * CurrencySwitcherBlock unit tests.
 */
class WCPay_Multi_Currency_Currency_Switcher_Block_Tests extends WP_UnitTestCase {

	/**
	 * @var CurrencySwitcherBlock
	 */
	protected $currency_switcher_block;

	/**
	 * @var MockObject|MultiCurrency
	 */
	protected $mock_multi_currency;

	/**
	 * @var MockObject|Compatibility
	 */
	protected $mock_compatibility;

	/**
	 * @var Currency[]
	 */
	protected $mock_currencies;

	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );
		$this->mock_compatibility  = $this->createMock( Compatibility::class );
		$this->mock_currencies     = [
			new Currency( 'USD', 1 ),
			new Currency( 'CAD', 1.206823 ),
			new Currency( 'GBP', 0.708099 ),
			new Currency( 'EUR', 0.826381 ),
		];

		$this->currency_switcher_block = new CurrencySwitcherBlock(
			$this->mock_multi_currency,
			$this->mock_compatibility
		);
	}

	/**
	 * @dataProvider block_widget_attributes_provider
	 */
	public function test_render_block_widget( $attributes, $test_styles ) {
		$flag   = $attributes['flag'] ?? false;
		$symbol = $attributes['symbol'] ?? true;

		$this->mock_compatibility->expects( $this->once() )
			->method( 'should_hide_widgets' )
			->willReturn( false );

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_enabled_currencies' )
			->willReturn( $this->mock_currencies );

		$result = $this->currency_switcher_block->render_block_widget( $attributes, '' );

		$this->assertStringContainsString( '<form>', $result );

		// Attributes are set with defaults in CurrencySwitcherBlock if they are not set, so we need to check them.
		$border_attribute        = isset( $attributes['border'] ) && $attributes['border'] ? '1px solid' : '0px solid';
		$border_radius_attribute = isset( $attributes['borderRadius'] ) ? $attributes['borderRadius'] . 'px' : '3px';
		$border_color_attribute  = $attributes['borderColor'] ?? '#000000';

		if ( $test_styles ) {
			$this->assertStringContainsString(
				"style=\"padding: 2px; border: {$border_attribute}; border-radius: {$border_radius_attribute}; border-color: {$border_color_attribute}; font-size: {$attributes['fontSize']}px; color: {$attributes['fontColor']}; background-color: {$attributes['backgroundColor']}; \"",
				$result
			);
		}

		$strings_to_test = [];
		if ( $flag && ! $symbol ) {
			$strings_to_test[] = '<option value="USD" >🇺🇸 USD</option>';
			$strings_to_test[] = '<option value="CAD" >🇨🇦 CAD</option>';
			$strings_to_test[] = '<option value="EUR" >🇪🇺 EUR</option>';
			$strings_to_test[] = '<option value="GBP" >🇬🇧 GBP</option>';
		} elseif ( ! $flag && $symbol ) {
			$strings_to_test[] = '<option value="USD" >$ USD</option>';
			$strings_to_test[] = '<option value="CAD" >$ CAD</option>';
			$strings_to_test[] = '<option value="EUR" >€ EUR</option>';
			$strings_to_test[] = '<option value="GBP" >£ GBP</option>';
		} elseif ( $flag && $symbol ) {
			$strings_to_test[] = '<option value="USD" >🇺🇸 $ USD</option>';
			$strings_to_test[] = '<option value="CAD" >🇨🇦 $ CAD</option>';
			$strings_to_test[] = '<option value="EUR" >🇪🇺 € EUR</option>';
			$strings_to_test[] = '<option value="GBP" >🇬🇧 £ GBP</option>';
		} else {
			$strings_to_test[] = '<option value="USD" >USD</option>';
			$strings_to_test[] = '<option value="CAD" >CAD</option>';
			$strings_to_test[] = '<option value="EUR" >EUR</option>';
			$strings_to_test[] = '<option value="GBP" >GBP</option>';
		}

		foreach ( $strings_to_test as $string ) {
			$this->assertStringContainsString( $string, html_entity_decode( $result ) );
		}
	}

	public function block_widget_attributes_provider(): array {
		return [
			'with_defaults'                 => [
				[],
				false,
			],
			'with_flag'                     => [
				[
					'symbol' => false,
					'flag'   => true,
				],
				false,
			],
			'with_symbol'                   => [
				[
					'symbol' => true,
					'flag'   => false,
				],
				false,
			],
			'with_flag_and_symbol'          => [
				[
					'symbol' => true,
					'flag'   => true,
				],
				false,
			],
			'with_styles_applied'           => [
				[
					'border'          => true,
					'borderRadius'    => 3,
					'symbol'          => false,
					'flag'            => false,
					'fontLineHeight'  => 1.2,
					'fontSize'        => 34,
					'fontColor'       => '#B00F0F',
					'backgroundColor' => '#E5FFCC',
				],
				true,
			],
			'with_styles_applied_no_border' => [
				[
					'border'          => false,
					'borderRadius'    => 2,
					'symbol'          => false,
					'flag'            => false,
					'fontLineHeight'  => 1.2,
					'fontSize'        => 34,
					'fontColor'       => '#B00F0F',
					'backgroundColor' => '#E5FFCC',
				],
				true,
			],
		];
	}

	public function test_widget_renders_hidden_input() {
		$_GET       = [
			'test_name'  => 'test_value',
			'test_array' => [ 0 => [ 0 => 'test_array_value' ] ],
			'named_key'  => [ 'key' => 'value' ],
		];
		$attributes = [
			[
				'symbol' => false,
				'flag'   => true,
			],
			false,
		];

		$this->mock_compatibility->expects( $this->once() )
			->method( 'should_hide_widgets' )
			->willReturn( false );

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_enabled_currencies' )
			->willReturn( $this->mock_currencies );

		$result = $this->currency_switcher_block->render_block_widget( $attributes, '' );
		$this->assertStringContainsString( '<input type="hidden" name="test_name" value="test_value" />', $result );
		$this->assertStringContainsString( '<input type="hidden" name="test_array[0][0]" value="test_array_value" />', $result );
		$this->assertStringContainsString( '<input type="hidden" name="named_key[key]" value="value" />', $result );
	}
}
