<?php
/**
 * Class WC_REST_Payments_Dispute_Readiness_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Internal\Service\DisputeReadinessService;

require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-dispute-readiness-controller.php';

/**
 * WC_REST_Payments_Dispute_Readiness_Controller unit tests.
 */
class WC_REST_Payments_Dispute_Readiness_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Dispute_Readiness_Controller
	 */
	private $controller;

	/**
	 * Service mock.
	 *
	 * @var DisputeReadinessService|PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;

	/**
	 * API client mock.
	 *
	 * @var WC_Payments_API_Client|PHPUnit\Framework\MockObject\MockObject
	 */
	private $api_client;

	public function set_up() {
		parent::set_up();

		$this->api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->service    = $this->createMock( DisputeReadinessService::class );
		$this->controller = new WC_REST_Payments_Dispute_Readiness_Controller( $this->api_client, $this->service );
	}

	public function tear_down() {
		delete_option( WC_Payments_Features::DISPUTE_READINESS_OVERVIEW );

		parent::tear_down();
	}

	public function test_get_readiness_returns_disabled_payload_when_feature_flag_is_off() {
		update_option( WC_Payments_Features::DISPUTE_READINESS_OVERVIEW, '0' );

		$disabled_payload = [
			'overview' => [
				'enabled' => false,
				'hidden'  => true,
			],
		];
		$this->service->expects( $this->once() )
			->method( 'get_disabled_overview_payload' )
			->willReturn( $disabled_payload );
		$this->service->expects( $this->never() )
			->method( 'get_overview_payload' );

		$response = $this->controller->get_readiness();

		$this->assertSame( $disabled_payload, $response->get_data() );
	}

	public function test_get_readiness_returns_overview_payload_by_default() {
		$payload = [
			'overview' => [
				'enabled' => true,
				'score'   => 3,
				'total'   => 4,
			],
		];
		$this->service->expects( $this->once() )
			->method( 'get_overview_payload' )
			->willReturn( $payload );

		$response = $this->controller->get_readiness();

		$this->assertSame( $payload, $response->get_data() );
	}

	public function test_dismiss_card_returns_error_when_feature_flag_is_off() {
		update_option( WC_Payments_Features::DISPUTE_READINESS_OVERVIEW, '0' );

		$this->service->expects( $this->never() )
			->method( 'dismiss_overview_card' );

		$response = $this->controller->dismiss_card();

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_dispute_readiness_disabled', $response->get_error_code() );
		$this->assertSame( 403, $response->get_error_data()['status'] );
	}

	public function test_dismiss_card_stores_and_returns_updated_payload_by_default() {
		$payload = [
			'overview' => [
				'enabled'     => true,
				'isDismissed' => true,
			],
		];
		$this->service->expects( $this->once() )
			->method( 'dismiss_overview_card' )
			->willReturn( $payload );

		$response = $this->controller->dismiss_card();

		$this->assertSame( $payload, $response->get_data() );
	}

	public function test_confirm_statement_descriptor_returns_error_when_feature_flag_is_off() {
		update_option( WC_Payments_Features::DISPUTE_READINESS_OVERVIEW, '0' );

		$this->service->expects( $this->never() )
			->method( 'confirm_statement_descriptor' );

		$response = $this->controller->confirm_statement_descriptor();

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_dispute_readiness_disabled', $response->get_error_code() );
		$this->assertSame( 403, $response->get_error_data()['status'] );
	}

	public function test_confirm_statement_descriptor_stores_and_returns_updated_payload_by_default() {
		$payload = [
			'overview' => [
				'enabled' => true,
				'score'   => 4,
			],
		];
		$this->service->expects( $this->once() )
			->method( 'confirm_statement_descriptor' )
			->willReturn( $payload );

		$response = $this->controller->confirm_statement_descriptor();

		$this->assertSame( $payload, $response->get_data() );
	}
}
