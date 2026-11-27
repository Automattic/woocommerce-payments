<?php
/**
 * Class WCPay\Tests\PaymentMethods\Configs\Definitions\LinkDefinitionTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\PaymentMethods\Configs\Definitions;

use WCPay\Constants\Country_Code;
use WCPay\PaymentMethods\Configs\Definitions\LinkDefinition;
use WCPAY_UnitTestCase;

/**
 * LinkDefinition unit tests.
 */
class LinkDefinitionTest extends WCPAY_UnitTestCase {

	/**
	 * @dataProvider provider_link_funding
	 */
	public function test_get_funding_source( ?array $payment_method_details, ?string $expected_funding_source, string $expected_title, ?string $expected_label ): void {
		$funding_source = LinkDefinition::get_funding_source( $payment_method_details );
		$title          = LinkDefinition::get_title_with_funding_source( Country_Code::UNITED_STATES, $payment_method_details );
		$label          = LinkDefinition::get_funding_source_label( $payment_method_details );

		$this->assertSame( $expected_funding_source, $funding_source );
		$this->assertSame( $expected_title, $title );
		$this->assertSame( $expected_label, $label );
	}

	public function provider_link_funding(): array {
		return [
			'bank account'              => [
				[
					'type' => 'link',
					'link' => [
						'country'              => 'COUNTRY_US',
						'funding_source_group' => 'lfsg_003',
					],
				],
				'bank_account',
				'Link (bank account)',
				'Bank account',
			],
			'card'                      => [
				[
					'type' => 'link',
					'link' => [
						'country'              => 'US',
						'funding_source_group' => 'lfsg_000',
					],
				],
				'card',
				'Link (card)',
				'Card',
			],
			'card with Link wallet'     => [
				[
					'type' => 'card',
					'card' => [
						'wallet' => [
							'type' => 'link',
							'link' => [ 'funding_source_group' => 'lfsg_003' ],
						],
					],
				],
				'bank_account',
				'Link (bank account)',
				'Bank account',
			],
			'Klarna group'              => [
				[
					'type' => 'link',
					'link' => [ 'funding_source_group' => 'lfsg_004' ],
				],
				null,
				'Link',
				null,
			],
			'no funding source group'   => [
				[
					'type' => 'link',
					'link' => [ 'country' => 'US' ],
				],
				null,
				'Link',
				null,
			],
			'no payment method details' => [ null, null, 'Link', null ],
		];
	}

	public function test_get_title_with_funding_source_uses_account_country_brand(): void {
		$payment_method_details = [
			'type' => 'link',
			'link' => [ 'funding_source_group' => 'lfsg_003' ],
		];

		$title = LinkDefinition::get_title_with_funding_source( Country_Code::UNITED_KINGDOM, $payment_method_details );

		$this->assertSame( 'Onelink (bank account)', $title );
	}
}
