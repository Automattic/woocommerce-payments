<?php
/**
 * Class WC_Payments_Captured_Event_Note_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Currency_Code;

/**
 * WC_Payments_Captured_Event_Note_Test unit tests.
 */
class WC_Payments_Captured_Event_Note_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Captured_Event_Note
	 */
	private $captured_event_note;

	/**
	 * @dataProvider provider
	 */
	public function test_strings_for_captured_event( array $captured_event, array $expectation ) {
		// Skip SEK tests for WooCommerce < 7.8.0 due to decimal formatting changes.
		$dataset_name = $this->dataName();
		if ( version_compare( WC_VERSION, '7.8.0', '<' ) && strpos( $dataset_name, '3-currencies' ) !== false ) {
			$this->markTestSkipped( 'SEK currency formatting changed in WooCommerce 7.8.0' );
		}

		$this->captured_event_note = new WC_Payments_Captured_Event_Note( $captured_event );

		$this->assertSame( $expectation['fxString'] ?? null, $this->captured_event_note->compose_fx_string() );
		$this->assertSame( $expectation['feeString'], $this->captured_event_note->compose_fee_string() );
		$this->assertSame( $expectation['feeBreakdown'] ?? null, $this->captured_event_note->get_fee_breakdown() );
		$this->assertSame( $expectation['netString'], $this->captured_event_note->compose_net_string() );
		$this->assertSame( $expectation['taxString'] ?? null, $this->captured_event_note->compose_tax_string() );
	}

	public function test_generate_html_note_envelope_path_renders_signed_discount_split() {
		$captured_event = [
			'type'                => 'captured',
			'amount'              => 1000,
			'amount_captured'     => 1000,
			'fee'                 => 57,
			'transaction_details' => [
				'customer_currency'        => Currency_Code::UNITED_STATES_DOLLAR,
				'customer_amount'          => 1000,
				'customer_amount_captured' => 1000,
				'customer_fee'             => 57,
				'store_currency'           => Currency_Code::UNITED_STATES_DOLLAR,
				'store_amount'             => 1000,
				'store_amount_captured'    => 1000,
				'store_fee'                => 57,
			],
			'fee_rates'           => [
				'percentage'     => 0.0425,
				'fixed'          => 28,
				'fixed_currency' => Currency_Code::UNITED_STATES_DOLLAR,
				'history'        => [
					[
						'type'            => 'base',
						'additional_type' => '',
						'fee_id'          => 'base-us-card-fee',
						'percentage_rate' => 0.029,
						'fixed_rate'      => 30,
						'currency'        => 'usd',
					],
				],
			],
			'fee_breakdown_v1'    => [
				'rows'    => [
					[
						'key'            => 'base',
						'kind'           => 'fee',
						'label'          => null,
						'amount'         => 59,
						'display_amount' => -59,
						'currency'       => 'usd',
						'rate'           => [
							'percentage'         => 0.029,
							'fixed'              => 30,
							'fixed_currency'     => 'usd',
							'percentage_display' => '2.9%',
						],
						'meta'           => null,
					],
					[
						'key'            => 'discount.wcpay-promo-2023',
						'kind'           => 'adjustment',
						'label'          => null,
						// Promo discount — negative deltas preserved through
						// envelope to renderer. The split below renders
						// "Variable fee: -0.15%" / "Fixed fee: -$0.02",
						// matching the legacy `compose_fee_break_down` output.
						'amount'         => 0,
						'display_amount' => 0,
						'currency'       => 'usd',
						'rate'           => [
							'percentage'         => -0.0015,
							'fixed'              => -2,
							'fixed_currency'     => 'usd',
							'percentage_display' => '-0.15%',
						],
						'meta'           => [ 'fee_id' => 'wcpay-promo-2023' ],
					],
				],
				'totals'  => [
					'fee'         => [
						'key'            => null,
						'amount'         => 57,
						'display_amount' => -57,
						'currency'       => 'usd',
						'rate'           => [
							'percentage'         => 0.0425,
							'fixed'              => 28,
							'fixed_currency'     => 'usd',
							'percentage_display' => '4.25%',
						],
					],
					'tax'         => [
						'amount'         => 0,
						'display_amount' => 0,
						'currency'       => 'usd',
					],
					'net'         => [
						'amount'   => 943,
						'currency' => 'usd',
					],
					'capture_net' => [
						'amount'   => 943,
						'currency' => 'usd',
					],
					'gross'       => [
						'amount'   => 1000,
						'currency' => 'usd',
					],
				],
				'notes'   => [],
				'sources' => [],
			],
		];

		$note = new WC_Payments_Captured_Event_Note( $captured_event );
		$html = $note->generate_html_note();

		// Envelope-path rendering of the discount row must mirror the
		// legacy `compose_fee_break_down` output: parent label line +
		// signed Variable / Fixed sub-bullets. Without the split, the
		// renderer would emit a single "Discount: -0.15% + -$0.02" line
		// — the legacy production format is the split form.
		$this->assertStringContainsString( 'Discount', $html );
		$this->assertStringContainsString( 'Variable fee: -0.15%', $html );
		$this->assertStringContainsString( 'Fixed fee: -$0.02', $html );

		// Fused single-line form must NOT appear — that's the regression
		// this test guards against.
		$this->assertStringNotContainsString( 'Discount: -0.15% + -$0.02', $html );
	}

	public function provider() {

		$res   = [];
		$files = glob( dirname( __DIR__, 1 ) . '/fixtures/captured-payments/*.json' );
		foreach ( $files as $file ) {
			$array_from_file = json_decode( file_get_contents( $file ), true ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$title           = $array_from_file['title'];
			$captured_event  = $array_from_file['capturedEvent'];
			$expectation     = $array_from_file['expectation'];

			$res[ $title ] = [ $captured_event, $expectation ];
		}

		return $res;
	}

	/**
	 * Shared base captured-event skeleton for envelope-driven tests. The
	 * envelope path (`generate_html_note_from_breakdown`) only reads
	 * `fee_breakdown_v1`; the legacy-composer fallback still needs a
	 * `fee_rates` + `transaction_details` pair so tests that exercise the
	 * `is_renderable_breakdown` false-path don't blow up inside the legacy
	 * chain.
	 *
	 * @param array $fee_breakdown_v1 Envelope payload to attach, or [] to omit entirely.
	 * @return array
	 */
	private function build_captured_event_with_envelope( array $fee_breakdown_v1 = [] ): array {
		$event = [
			'type'                => 'captured',
			'amount'              => 1000,
			'amount_captured'     => 1000,
			'fee'                 => 59,
			'fee_rates'           => [
				'percentage'     => 0.029,
				'fixed'          => 30,
				'fixed_currency' => Currency_Code::UNITED_STATES_DOLLAR,
				'history'        => [
					[
						'type'            => 'base',
						'additional_type' => '',
						'fee_id'          => 'base-us-card-fee',
						'percentage_rate' => 0.029,
						'fixed_rate'      => 30,
						'currency'        => 'usd',
					],
				],
			],
			'transaction_details' => [
				'customer_currency'        => Currency_Code::UNITED_STATES_DOLLAR,
				'customer_amount'          => 1000,
				'customer_amount_captured' => 1000,
				'customer_fee'             => 59,
				'store_currency'           => Currency_Code::UNITED_STATES_DOLLAR,
				'store_amount'             => 1000,
				'store_amount_captured'    => 1000,
				'store_fee'                => 59,
			],
		];
		if ( ! empty( $fee_breakdown_v1 ) ) {
			$event['fee_breakdown_v1'] = $fee_breakdown_v1;
		}
		return $event;
	}

	/**
	 * Minimal well-formed `fee_breakdown_v1` envelope — one base-fee row,
	 * no tax, no notes. Tests that care about a specific field override it
	 * on top of this skeleton.
	 *
	 * @return array
	 */
	private function build_minimal_envelope(): array {
		return [
			'rows'   => [
				[
					'key'            => 'base',
					'kind'           => 'fee',
					'label'          => null,
					'amount'         => 59,
					'display_amount' => -59,
					'currency'       => Currency_Code::UNITED_STATES_DOLLAR,
					'rate'           => [
						'percentage'         => 0.029,
						'fixed'              => 30,
						'fixed_currency'     => Currency_Code::UNITED_STATES_DOLLAR,
						'percentage_display' => '2.9%',
					],
					'meta'           => null,
				],
			],
			'totals' => [
				'fee'         => [
					'amount'         => 59,
					'display_amount' => -59,
					'currency'       => Currency_Code::UNITED_STATES_DOLLAR,
					'key'            => null,
					'rate'           => [
						'percentage'         => 0.029,
						'fixed'              => 30,
						'fixed_currency'     => Currency_Code::UNITED_STATES_DOLLAR,
						'percentage_display' => '2.9%',
					],
				],
				'tax'         => [
					'amount'         => 0,
					'display_amount' => 0,
					'currency'       => Currency_Code::UNITED_STATES_DOLLAR,
				],
				'net'         => [
					'amount'   => 941,
					'currency' => Currency_Code::UNITED_STATES_DOLLAR,
				],
				'capture_net' => [
					'amount'   => 941,
					'currency' => Currency_Code::UNITED_STATES_DOLLAR,
				],
				'gross'       => [
					'amount'   => 1000,
					'currency' => Currency_Code::UNITED_STATES_DOLLAR,
				],
			],
			'notes'  => [],
		];
	}

	public function test_generate_html_note_from_breakdown_happy_path(): void {
		$event = $this->build_captured_event_with_envelope( $this->build_minimal_envelope() );
		$html  = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		$this->assertStringContainsString( '<div class="captured-event-details">', $html );
		// Fee line renders with rate + amount as "Fee (2.9% + $0.30): -$0.59".
		$this->assertMatchesRegularExpression( '/<p>Fee \(2\.9% \+ \$0\.30\): .*0\.59.*<\/p>/', $html );
		$this->assertMatchesRegularExpression( '/<p>Net payout: .*9\.41.*<\/p>/', $html );
	}

	public function test_generate_html_note_from_breakdown_processing_fee_totals_key(): void {
		$envelope                          = $this->build_minimal_envelope();
		$envelope['totals']['fee']['key']  = 'processing_fee';
		$envelope['totals']['fee']['rate'] = null;
		$event                             = $this->build_captured_event_with_envelope( $envelope );
		$html                              = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		// With `processing_fee` as the totals key the top-line label
		// flips to "Processing fee" and the rate suffix is dropped — matching
		// the Amazon Pay non-card case where WooPayments refunded its
		// application fee and only Stripe's passthrough remains.
		$this->assertMatchesRegularExpression( '/<p>Processing fee: .*0\.59.*<\/p>/', $html );
		$this->assertStringNotContainsString( '<p>Fee (2.9%', $html );
	}

	public function test_generate_html_note_from_breakdown_application_fee_refunded_note_with_amount(): void {
		$envelope            = $this->build_minimal_envelope();
		$envelope['notes'][] = [
			'code'     => 'application_fee_refunded',
			'severity' => 'info',
			'meta'     => [
				'refunded_amount'   => 41,
				'refunded_currency' => Currency_Code::UNITED_STATES_DOLLAR,
				'reason'            => 'amazon_pay_non_card_double_fee',
			],
		];
		$event               = $this->build_captured_event_with_envelope( $envelope );
		$html                = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		$this->assertStringContainsString(
			'WooPayments refunded its $0.41 USD application fee on this transaction.',
			$html
		);
	}

	public function test_generate_html_note_from_breakdown_application_fee_refunded_note_without_amount(): void {
		$envelope            = $this->build_minimal_envelope();
		$envelope['notes'][] = [
			'code'     => 'application_fee_refunded',
			'severity' => 'info',
			'meta'     => [
				// No `refunded_amount` — defensive path for old envelopes
				// or a server-side omission. Renderer drops the amount and
				// uses the generic copy.
				'refunded_currency' => Currency_Code::UNITED_STATES_DOLLAR,
				'reason'            => 'amazon_pay_non_card_double_fee',
			],
		];
		$event               = $this->build_captured_event_with_envelope( $envelope );
		$html                = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		$this->assertStringContainsString(
			'WooPayments refunded its application fee on this transaction.',
			$html
		);
		// Make sure the amount-bearing variant isn't emitted as a leak.
		$this->assertStringNotContainsString( 'refunded its $', $html );
	}

	public function test_generate_html_note_from_breakdown_tax_row_rendering(): void {
		$envelope                  = $this->build_minimal_envelope();
		$envelope['rows'][]        = [
			'key'            => 'tax_on_fee',
			'kind'           => 'tax',
			'label'          => 'IT VAT',
			'amount'         => 13,
			'display_amount' => -13,
			'currency'       => Currency_Code::UNITED_STATES_DOLLAR,
			'rate'           => [
				'percentage'         => 0.22,
				'percentage_display' => '22.00%',
			],
			'meta'           => null,
		];
		$envelope['totals']['tax'] = [
			'amount'         => 13,
			'display_amount' => -13,
			'currency'       => Currency_Code::UNITED_STATES_DOLLAR,
		];
		$event                     = $this->build_captured_event_with_envelope( $envelope );
		$html                      = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		// Tax line carries the localized description + percentage + signed amount.
		$this->assertMatchesRegularExpression(
			'/<p>Tax IT VAT \(22\.00%\): .*0\.13.*<\/p>/',
			$html
		);
	}

	public function test_generate_html_note_from_breakdown_escapes_hostile_row_label_and_refunded_currency(): void {
		// Envelope values are server-generated by Fee_Breakdown_Builder and
		// shipped via signed transport, but the HTML rendered here is
		// persisted as an order note (the historical DB record), so the
		// renderer must defend against attacker-controlled strings that
		// could otherwise land verbatim in merchant-visible HTML.
		$envelope            = $this->build_minimal_envelope();
		$envelope['rows'][]  = [
			'key'            => 'additional.custom',
			'kind'           => 'fee',
			'label'          => '<script>alert("row")</script>',
			'amount'         => 10,
			'display_amount' => -10,
			'currency'       => Currency_Code::UNITED_STATES_DOLLAR,
			'rate'           => null,
			'meta'           => null,
		];
		$envelope['notes'][] = [
			'code'     => 'application_fee_refunded',
			'severity' => 'info',
			'meta'     => [
				'refunded_amount'   => 41,
				// Hostile currency — `format_explicit_currency` concats
				// the raw code when it isn't found in the formatted
				// amount. Renderer must escape before persisting.
				'refunded_currency' => '<img src=x onerror=alert("xss")>',
				'reason'            => 'amazon_pay_non_card_double_fee',
			],
		];
		$event               = $this->build_captured_event_with_envelope( $envelope );
		$html                = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		// Hostile strings must not appear as raw HTML tags anywhere in
		// the rendered note. The entity-encoded form is fine — the text
		// *between* `&lt;` and `&gt;` cannot be interpreted as markup.
		// `format_explicit_currency` uppercases the currency code, so the
		// img payload lands as `<IMG ...>` → `&lt;IMG ...&gt;`.
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '</script>', $html );
		$this->assertStringNotContainsString( '<img ', $html );
		$this->assertStringNotContainsString( '<IMG ', $html );

		// Entity-encoded forms are fine — they render as text, not markup.
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertMatchesRegularExpression( '/&lt;IMG SRC=X/i', $html );
	}

	public function test_generate_html_note_falls_back_to_legacy_composer_when_envelope_is_not_renderable(): void {
		// Envelope is malformed: totals.fee.amount present, but `rows` is
		// missing entirely → `is_renderable_breakdown` returns false and
		// `generate_html_note()` routes through the legacy fee_rates chain.
		$malformed_envelope = [
			'totals' => [
				'fee' => [
					'amount'   => 59,
					'currency' => Currency_Code::UNITED_STATES_DOLLAR,
					'key'      => 'processing_fee',
				],
				'tax' => [ 'amount' => 0 ],
			],
			// `rows` intentionally absent.
		];
		$event = $this->build_captured_event_with_envelope( $malformed_envelope );
		$html  = ( new WC_Payments_Captured_Event_Note( $event ) )->generate_html_note();

		// Legacy composer output: "Base fee" top line from `compose_fee_string`.
		// If the renderer had used the envelope path, the key `processing_fee`
		// would have produced "Processing fee" instead.
		$this->assertStringContainsString( 'Base fee', $html );
		$this->assertStringNotContainsString( 'Processing fee', $html );
	}
}
