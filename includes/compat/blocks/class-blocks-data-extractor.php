<?php
/**
 * Class Blocks_Data_Extractor
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;
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
	 * Constructor.
	 */
	public function __construct() {
		$this->integration_registry = new IntegrationRegistry();
	}

	/**
	 * Get a list of available Blocks.
	 *
	 * @return array
	 */
	private function get_available_blocks() {
		$blocks = [];
		if ( class_exists( '\AutomateWoo\Blocks\Marketing_Optin_Block' ) ) {
			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedClass
			 * @phpstan-ignore-next-line
			 */
			$blocks[] = new \Automatewoo\Blocks\Marketing_Optin_Block();
		}

		if ( class_exists( '\Mailchimp_Woocommerce_Newsletter_Blocks_Integration' ) ) {
			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedClass
			 * @phpstan-ignore-next-line
			 */
			$blocks[] = new \Mailchimp_Woocommerce_Newsletter_Blocks_Integration();
		}

		if ( class_exists( '\WCK\Blocks\CheckoutIntegration' ) ) {
			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedClass
			 * @phpstan-ignore-next-line
			 */
			$blocks[] = new \WCK\Blocks\CheckoutIntegration();
		}

		return $blocks;
	}

	/**
	 * Register all the blocks.
	 *
	 * @param array $blocks A list of blocks to register.
	 * @return void
	 */
	private function register_blocks( $blocks ) {
		foreach ( $blocks as $block ) {
			$this->integration_registry->register( $block );
		}
	}

	/**
	 * Unregister all blocks.
	 *
	 * @param array $blocks A list of blocks to unregister.
	 * @return void
	 */
	private function unregister_blocks( $blocks ) {
		foreach ( $blocks as $block ) {
			$this->integration_registry->unregister( $block );
		}
	}

	/**
	 * Mailpoet's block registration is different from the other two plugins. Data fields are passed
	 * from the parent class. This method fetches the data fields without registering the plugin.
	 *
	 * @return array
	 */
	private function get_mailpoet_data() {
		// phpcs:ignore
		/**
		 * We check whether relevant MailPoet classes exists before invoking this method.
		 *
		 * @psalm-suppress UndefinedClass
		 * @phpstan-ignore-next-line
		 */
		$mailpoet_wc_subscription = \MailPoet\DI\ContainerWrapper::getInstance()->get( \MailPoet\WooCommerce\Subscription::class );
		// phpcs:ignore
		/**
		 * @psalm-suppress UndefinedClass
		 * @phpstan-ignore-next-line
		 */
		$settings_instance = \MailPoet\Settings\SettingsController::getInstance();
		$settings          = [
			'defaultText'   => $settings_instance->get( 'woocommerce.optin_on_checkout.message', '' ),
			'optinEnabled'  => $settings_instance->get( 'woocommerce.optin_on_checkout.enabled', false ),
			'defaultStatus' => false,
		];

		if ( version_compare( \MAILPOET_VERSION, '4.18.0', '<=' ) ) {
			$settings['defaultStatus'] = $mailpoet_wc_subscription->isCurrentUserSubscribed();
		}
		return $settings;
	}

	/**
	 * Retrieve data fields.
	 *
	 * @return array
	 */
	public function get_data() {
		$blocks = $this->get_available_blocks();

		$this->register_blocks( $blocks );

		$blocks_data = $this->integration_registry->get_all_registered_script_data();

		if ( class_exists( 'MailPoet\DI\ContainerWrapper' ) && class_exists( 'MailPoet\WooCommerce\Subscription' ) ) {
			$blocks_data += [ 'mailpoet_data' => $this->get_mailpoet_data() ];
		}

		$this->unregister_blocks( $blocks );

		return $blocks_data;
	}

	/**
	 * Retrieves the namespaces in the Store API checkout schema.
	 *
	 * @return array
	 */
	public function get_checkout_schema_namespaces(): array {
		$namespaces = [];

		if (
			class_exists( 'Automattic\WooCommerce\StoreApi\StoreApi' ) &&
			class_exists( 'Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema' ) &&
			class_exists( 'Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema' )
		) {
			try {
				$checkout_schema = StoreApi::container()->get( ExtendSchema::class )->get_endpoint_schema( CheckoutSchema::IDENTIFIER );
			} catch ( \Exception $e ) {
				return $namespaces;
			}

			$namespaces = array_keys( (array) $checkout_schema );
		}

		return $namespaces;
	}
}
