<?php
/**
 * Tests the registration arguments shared by every WooPayments ability.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain;

/**
 * Checks each ability's name, annotations and required input in one table.
 *
 * Ability-specific schema properties are tested in each ability's own test class.
 */
class AbilityRegistrationTest extends WCPAY_UnitTestCase {

	public function test_table_covers_every_registered_ability(): void {
		$reflection = new \ReflectionClass( AbilitiesRegistrar::class );
		$registered = $reflection->getReflectionConstant( 'ABILITY_CLASSES' )->getValue();

		$tested = array_column( $this->provider_abilities(), 0 );

		sort( $registered );
		sort( $tested );
		$this->assertSame( $registered, $tested );
	}

	/**
	 * @dataProvider provider_abilities
	 */
	public function test_registration_args( string $ability_class, string $name, bool $is_readonly, bool $destructive, bool $idempotent, bool $mcp_public, array $required ): void {
		$args = $ability_class::get_registration_args();

		$this->assertSame( $name, $ability_class::get_name() );
		$this->assertSame( 'woocommerce', $args['category'] );
		$this->assertSame( [ $ability_class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertSame( $is_readonly, $args['meta']['annotations']['readonly'] );
		$this->assertSame( $destructive, $args['meta']['annotations']['destructive'] );
		$this->assertSame( $idempotent, $args['meta']['annotations']['idempotent'] );
		$this->assertSame( $mcp_public, $args['meta']['mcp']['public'] );
		$this->assertSame( 'object', $args['input_schema']['type'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
		$this->assertSame( $required, $args['input_schema']['required'] ?? [] );
	}

	/**
	 * Columns: class, name, readonly, destructive, idempotent, MCP public, required input.
	 */
	public function provider_abilities(): array {
		return [
			'get account'                  => [ Domain\GetAccount::class, 'woocommerce-payments/get-account', true, false, true, true, [] ],
			'get deposits overview'        => [ Domain\GetDepositsOverview::class, 'woocommerce-payments/get-deposits-overview', true, false, true, true, [] ],
			'get active loan summary'      => [ Domain\GetActiveLoanSummary::class, 'woocommerce-payments/get-active-loan-summary', true, false, true, true, [] ],
			'get transactions summary'     => [ Domain\GetTransactionsSummary::class, 'woocommerce-payments/get-transactions-summary', true, false, true, true, [] ],
			'get disputes summary'         => [ Domain\GetDisputesSummary::class, 'woocommerce-payments/get-disputes-summary', true, false, true, true, [] ],
			'get authorizations summary'   => [ Domain\GetAuthorizationsSummary::class, 'woocommerce-payments/get-authorizations-summary', true, false, true, true, [] ],
			'get deposits summary'         => [ Domain\GetDepositsSummary::class, 'woocommerce-payments/get-deposits-summary', true, false, true, true, [] ],
			'get dispute'                  => [ Domain\GetDispute::class, 'woocommerce-payments/get-dispute', true, false, true, true, [ 'dispute_id' ] ],
			'get payment intent'           => [ Domain\GetPaymentIntent::class, 'woocommerce-payments/get-payment-intent', true, false, true, true, [ 'payment_intent_id' ] ],
			'get charge'                   => [ Domain\GetCharge::class, 'woocommerce-payments/get-charge', true, false, true, true, [ 'charge_id' ] ],
			'get timeline'                 => [ Domain\GetTimeline::class, 'woocommerce-payments/get-timeline', true, false, true, true, [ 'intention_id' ] ],
			'get transactions'             => [ Domain\GetTransactions::class, 'woocommerce-payments/get-transactions', true, false, true, true, [] ],
			'get disputes'                 => [ Domain\GetDisputes::class, 'woocommerce-payments/get-disputes', true, false, true, true, [] ],
			'get authorizations'           => [ Domain\GetAuthorizations::class, 'woocommerce-payments/get-authorizations', true, false, true, true, [] ],
			'get deposits'                 => [ Domain\GetDeposits::class, 'woocommerce-payments/get-deposits', true, false, true, true, [] ],
			'get balance'                  => [ Domain\GetBalance::class, 'woocommerce-payments/get-balance', true, false, true, true, [ 'date_start', 'date_end', 'currency' ] ],
			'get fraud outcomes'           => [ Domain\GetFraudOutcomes::class, 'woocommerce-payments/get-fraud-outcomes', true, false, true, true, [ 'status' ] ],
			'get fees summary'             => [ Domain\GetFeesSummary::class, 'woocommerce-payments/get-fees-summary', true, false, true, true, [] ],
			'refund charge'                => [ Domain\RefundCharge::class, 'woocommerce-payments/refund-charge', false, true, true, false, [ 'charge_id', 'idempotency_key' ] ],
			'submit dispute evidence'      => [ Domain\SubmitDisputeEvidence::class, 'woocommerce-payments/submit-dispute-evidence', false, true, false, false, [ 'dispute_id' ] ],
			'accept dispute'               => [ Domain\AcceptDispute::class, 'woocommerce-payments/accept-dispute', false, true, false, false, [ 'dispute_id' ] ],
			'upload dispute evidence file' => [ Domain\UploadDisputeEvidenceFile::class, 'woocommerce-payments/upload-dispute-evidence-file', false, false, false, false, [ 'file_name', 'file_type', 'file_contents' ] ],
		];
	}
}
