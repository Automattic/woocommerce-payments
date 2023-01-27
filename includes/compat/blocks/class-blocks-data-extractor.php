<?php
/**
 * Class Asset_Data_Registry_Wrapper
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;

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
		$this->blocks               = $this->get_available_blocks();

		$this->register_blocks();
	}

	/**
	 * Get a list of available Blocks.
	 */
	private function get_available_blocks() {
		$blocks = [];
		if ( class_exists( '\AutomateWoo\Blocks\Marketing_Optin_Block' ) ) {
			array_push( $blocks, new Marketing_Optin_Block() );
		}

		if ( class_exists( '\Mailchimp_Woocommerce_Newsletter_Blocks_Integration' ) ) {
			array_push( $blocks, new \Mailchimp_Woocommerce_Newsletter_Blocks_Integration() );
		}

		return $blocks;
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
	 *  Mailpoet's block registration is different from the other two plugins. Data fields are passed
	 *  from the parent class. This method fetches the data fields without registering the plugin.
	 */
	private function get_mailpoet_data() {
		$this->mailpoet_wc_subscription = \MailPoet\DI\ContainerWrapper::getInstance()->get( \MailPoet\WooCommerce\Subscription::class );
		$settings_instance              = \MailPoet\Settings\SettingsController::getInstance();
		$settings                       = [
			'defaultText'   => $settings_instance->get( 'woocommerce.optin_on_checkout.message', '' ),
			'optinEnabled'  => $settings_instance->get( 'woocommerce.optin_on_checkout.enabled', false ),
			'defaultStatus' => $this->mailpoet_wc_subscription->isCurrentUserSubscribed(),
		];
		return $settings;
	}

	/**
	 * Retrieve data fields.
	 */
	public function get_data() {
		$blocks_data = $this->integration_registry->get_all_registered_script_data();

		if ( class_exists( 'MailPoet\DI\ContainerWrapper' ) && class_exists( 'MailPoet\WooCommerce\Subscription' ) ) {
			$mailpoet_data = [ 'mailpoet_data' => $this->get_mailpoet_data() ];
			$blocks_data   = array_merge( $blocks_data, $mailpoet_data );
		}

		return $blocks_data;
	}
}
