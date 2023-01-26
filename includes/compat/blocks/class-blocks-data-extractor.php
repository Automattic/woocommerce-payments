<?php
/**
 * Class Asset_Data_Registry_Wrapper
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use AutomateWoo\Blocks\Marketing_Optin_Block;
use MailPoet\Settings\SettingsController as mailpoet_settings;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Extract data fields from certain block based plugins.
 */
class Blocks_Data_Extractor {

	/**
	 * Instance of the integration registry.
	 *
	 * @var IntegrationRegistry
	 */
	private $integration_registry;

	/**
	 * An array of blocks to extract data fields.
	 *
	 * @var IntegrationRegistry
	 */
	private $blocks = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->integration_registry = new IntegrationRegistry();
		$this->blocks               = [ new Marketing_Optin_Block(), new \Mailchimp_Woocommerce_Newsletter_Blocks_Integration() ];
		$this->register_blocks();
	}

	/**
	 * Register all the blocks.
	 */
	private function register_blocks() {
		foreach ( $this->blocks as $block ) {
			$this->integration_registry->register( $block );
		}
	}

	/**
	 * Unregister all the blocks.
	 */
	private function unregister_blocks() {
		foreach ( $this->blocks as $block ) {
			$this->integration_registry->unregister( $block );
		}
	}

	/**
	 *  Mailpoet's block registration is different from the other two plugins. Data fields are passed
	 *  from the parent class. This method fetches the data fields without registering the plugin.
	 */
	private function get_mailpoet_data() {
		$settings_instance = mailpoet_settings::getInstance();
		$settings          = [
			'defaultText'  => $settings_instance->get( 'woocommerce.optin_on_checkout.message', '' ),
			'optinEnabled' => $settings_instance->get( 'woocommerce.optin_on_checkout.enabled', false ),
			// TODO: fix this
			// 'defaultStatus' => $this->woocommerceSubscription->isCurrentUserSubscribed().
		];
		return $settings;
	}

	/**
	 * Retrieve data fields.
	 */
	public function get_data() {
		$blocks_data   = $this->integration_registry->get_all_registered_script_data();
		$mailpoet_data = [ 'mailpoet_data' => $this->get_mailpoet_data() ];

		return array_merge( $blocks_data, $mailpoet_data );
	}
}
