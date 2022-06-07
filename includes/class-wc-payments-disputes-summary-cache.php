<?php
/**
 * WC_Payments_Disputes_Summary_Cache class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class which manages the payment dispute summary cache.
 */
class WC_Payments_Disputes_Summary_Cache {

	/**
	 * The WP option key that the disputes summary is cached with.
	 *
	 * @var string
	 */
	const CACHE_OPTION_KEY = 'wc_payments_disputes_summary';

	/**
	 * An instance of the WC_Payments_API_Client
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Constructor
	 *
	 * @param WC_Payments_API_Client $api_client WC_Payments_API_Client instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->payments_api_client = $api_client;
	}

	/**
	 * Gets the disputes summary.
	 *
	 * The summary includes:
	 *  - the total number of disputes
	 *  - a breakdown of disputes per status
	 *  - the currency codes with a dispute
	 *
	 * @since 4.4.0
	 *
	 * @return array The disputes summary.
	 */
	public function get_dispute_summary() {
		$disputes_summary = get_option( self::CACHE_OPTION_KEY );

		if ( empty( $disputes_summary ) ) {
			$disputes_summary = $this->payments_api_client->get_disputes_summary();
			update_option( self::CACHE_OPTION_KEY, $disputes_summary );
		}

		return $disputes_summary;
	}

	/**
	 * Clears the disputes summary cache.
	 *
	 * @since 4.4.0
	 */
	public function clear_cache() {
		delete_option( self::CACHE_OPTION_KEY );
	}

	/**
	 * Gets the number of disputes that need a response.
	 *
	 * @since 4.4.0
	 * @return int The number of disputes with the 'needs_response' status.
	 */
	public function get_disputes_needing_response_count() {
		$disputes_summary = $this->get_dispute_summary();
		return isset( $disputes_summary['statuses']['needs_response'] ) ? absint( $disputes_summary['statuses']['needs_response'] ) : 0;
	}
}
