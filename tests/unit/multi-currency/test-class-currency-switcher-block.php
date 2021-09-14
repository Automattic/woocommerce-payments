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

		/**
		 * Mock available currencies with their rates.
		 *
		 * @var array
		 */
	private $mock_available_currencies = [
		'USD' => 1,
		'CAD' => 1.206823,
		'GBP' => 0.708099,
		'EUR' => 0.826381,
		'CDF' => 2000,
		'BIF' => 1974, // Zero decimal currency.
		'CLP' => 706.8, // Zero decimal currency.
	];
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
	 * @group underTest
	 * @dataProvider block_widget_attributes_provider
	 */
	public function test_render_block_widget( $attributes ) {
		// Use the same defaults as the function.
		$title  = $attributes['title'] ?? '';
		$flag   = $attributes['flag'] ?? false;
		$symbol = $attributes['symbol'] ?? true;

		$this->mock_compatibility->expects( $this->once() )
			->method( 'should_hide_widgets' )
			->willReturn( false );

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_enabled_currencies' )
			->willReturn( $this->mock_currencies );

		$result = $this->currency_switcher_block->render_block_widget( $attributes, '' );

		if ( '' !== ( $title ?? '' ) ) {
			$this->assertStringContainsString(
				'<span class="gamma widget-title">' . $title . '</span>',
				$result
			);
		} else {
			$this->assertStringNotContainsString(
				'<span class="gamma widget-title">',
				$result
			);
		}

		$this->assertStringContainsString( '<form>', $result );

		if ( $flag && ! $symbol ) {
			$this->assertStringContainsString( '<option value="USD" >🇺🇸 USD</option>', $result );
			$this->assertStringContainsString( '<option value="CAD" >🇨🇦 CAD</option>', $result );
			$this->assertStringContainsString( '<option value="EUR" >🇪🇺 EUR</option>', $result );
			$this->assertStringContainsString( '<option value="GBP" >🇬🇧 GBP</option>', $result );
		} elseif ( ! $flag && $symbol ) {
			$this->assertStringContainsString( '<option value="USD" >$ USD</option>', $result );
			$this->assertStringContainsString( '<option value="CAD" >$ CAD</option>', $result );
			$this->assertStringContainsString( '<option value="EUR" >€ EUR</option>', $result );
			$this->assertStringContainsString( '<option value="GBP" >£ GBP</option>', $result );
		} elseif ( $flag && $symbol ) {
			$this->assertStringContainsString( '<option value="USD" >🇺🇸 $ USD</option>', $result );
			$this->assertStringContainsString( '<option value="CAD" >🇨🇦 $ CAD</option>', $result );
			$this->assertStringContainsString( '<option value="EUR" >🇪🇺 € EUR</option>', $result );
			$this->assertStringContainsString( '<option value="GBP" >🇬🇧 £ GBP</option>', $result );
		} else {
			$this->assertStringContainsString( '<option value="USD" >USD</option>', $result );
			$this->assertStringContainsString( '<option value="CAD" >CAD</option>', $result );
			$this->assertStringContainsString( '<option value="EUR" >EUR</option>', $result );
			$this->assertStringContainsString( '<option value="GBP" >GBP</option>', $result );
		}
	}

	public function block_widget_attributes_provider() {
		return [
			'with_defaults'        => [
				[],
			],
			'with_title'           => [
				[
					'title'  => 'Test',
					'symbol' => false,
					'flag'   => false,
				],
			],
			'without_title'        => [
				[
					'title'  => '',
					'symbol' => false,
					'flag'   => false,
				],
			],
			'with_flag'            => [
				[
					'title'  => 'Test',
					'symbol' => false,
					'flag'   => true,
				],
			],
			'with_symbol'          => [
				[
					'title'  => 'Test',
					'symbol' => true,
					'flag'   => false,
				],
			],
			'with_flag_and_symbol' => [
				[
					'title'  => 'Test',
					'symbol' => true,
					'flag'   => true,
				],
			],
		];
	}
}
