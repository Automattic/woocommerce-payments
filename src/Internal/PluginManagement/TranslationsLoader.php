<?php
/**
 * Class TranslationsLoader
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\PluginManagement;

use DateTime;
use WCPay\Internal\Logger;
use WCPay\Internal\Proxy\HooksProxy;


/**
 * A class for managing WordPress translations loading for WooPayments.
 *
 * Unlike for other WordPress.org plugins, WooPayments translations are loaded from https://translate.wordpress.com.
 */
class TranslationsLoader {
	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * HooksProxy instance.
	 *
	 * @var HooksProxy
	 */
	private $hooks_proxy;

	/**
	 * Initializes all dependencies.
	 *
	 * @param Logger     $logger          Logger instance.
	 * @param HooksProxy $hooks_proxy     Hooks proxy instance.
	 */
	public function __construct( Logger $logger, HooksProxy $hooks_proxy ) {
		$this->logger      = $logger;
		$this->hooks_proxy = $hooks_proxy;
	}


	/**
	 * Hooks into WordPress plugin update process to load plugin translations from translate.wordpress.com.
	 */
	public function init_hooks() {
		$this->hooks_proxy->add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'load_wcpay_translations' ] );
	}

	/**
	 * Hooks into auto-update process to load plugin translations from translate.wordpress.com.
	 *
	 * Runs in a cron thread, or in a visitor thread if triggered
	 * by _maybe_update_plugins(), or in an auto-update thread.
	 *
	 * @param object $transient The update_plugins transient object.
	 *
	 * @return object The same or a modified version of the transient.
	 */
	public function load_wcpay_translations( $transient ) {
		try {
			if ( is_object( $transient ) ) {
				$translations            = $this->get_translations_update_data();
				$merged_translations     = array_merge( isset( $transient->translations ) ? $transient->translations : [], $translations );
				$transient->translations = $merged_translations;
			}
		} catch ( \Exception $ex ) {
			$this->logger->error( 'Error with loading WooPayments translations from WordPress.com. Reason: ' . $ex->getMessage() );
			return $transient;
		}
		return $transient;
	}

	/**
	 * Get translations updates information.
	 *
	 * @return array Update data {product_id => data}
	 */
	public function get_translations_update_data() {
		$installed_translations = wp_get_installed_translations( 'plugins' );
		$locales                = array_values( get_available_languages() );

		if ( empty( $locales ) ) {
			return [];
		}

		// Use the same timeout values as Woo Core https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/admin/helper/class-wc-helper-updater.php#L257.
		$timeout     = wp_doing_cron() ? 30 : 3;
		$plugin_name = 'woocommerce-payments'; // TODO: check if there is a better way of getting the plugin name.

		$request_body = [
			'locales' => $locales,
			'plugins' => [],
		];

		$request_body['plugins'][ $plugin_name ] = [
			'version' => WCPAY_VERSION_NUMBER,
		];

		$raw_response = wp_remote_post(
			'https://translate.wordpress.com/api/translations-updates/woocommerce',
			[
				'body'    => wp_json_encode( $request_body ),
				'headers' => [ 'Content-Type: application/json' ],
				'timeout' => $timeout,
			]
		);

		// Something wrong happened on the translate server side.
		$response_code = wp_remote_retrieve_response_code( $raw_response );
		if ( 200 !== $response_code ) {
			return [];
		}

		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

		// API error, api returned but something was wrong.
		if ( array_key_exists( 'success', $response ) && false === $response['success'] ) {
			return [];
		}

		$language_packs = $response['data'][ $plugin_name ];

		$translations = [];

		foreach ( $language_packs as $language_pack ) {
			// Maybe we have this language pack already installed so lets check revision date.
			if ( array_key_exists( $plugin_name, $installed_translations ) && array_key_exists( $language_pack['wp_locale'], $installed_translations[ $plugin_name ] ) ) {
				$installed_translation_revision_time = new DateTime( $installed_translations[ $plugin_name ][ $language_pack['wp_locale'] ]['PO-Revision-Date'] );
				$new_translation_revision_time       = new DateTime( $language_pack['last_modified'] );
				// Skip if translation language pack is not newer than what is installed already.
				if ( $new_translation_revision_time <= $installed_translation_revision_time ) {
					continue;
				}
			}
			$translations[] = [
				'type'       => 'plugin',
				'slug'       => $plugin_name,
				'language'   => $language_pack['wp_locale'],
				'version'    => $language_pack['version'],
				'updated'    => $language_pack['last_modified'],
				'package'    => $language_pack['package'],
				'autoupdate' => true,
			];
		}

		return $translations;
	}
}
