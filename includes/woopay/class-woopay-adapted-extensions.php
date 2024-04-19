<?php
/**
 * WooPay
 *
 * @package WCPay\WooPay
 */

namespace WCPay\WooPay;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * WooPay
 */
class WooPay_Adapted_Extensions extends IntegrationRegistry {
	const POINTS_AND_REWARDS_PLUGIN = 'woocommerce-points-and-rewards';
	const POINTS_AND_REWARDS_API    = 'points-and-rewards';
	const GIFT_CARDS_API            = 'woocommerce-gift-cards';
	const GIFT_CARDS_BLOCKS         = 'wc-gift-cards-blocks';

	/**
	 * Initializa WC Blocks regitered integrations.
	 */
	public function init() {
		do_action( 'woocommerce_blocks_checkout_block_registration', $this );
	}

	/**
	 * Get WooPay adapted extensions settings and extra data needed on WooPay.
	 *
	 * @param string $email The user email the data will be loaded.
	 *
	 * @return array The extensions script data.
	 */
	public function get_adapted_extensions_data( $email ) {
		$enabled_adapted_extensions = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );

		if ( (is_countable($enabled_adapted_extensions) ? count( $enabled_adapted_extensions ) : 0) === 0 ) {
			return [];
		}

		$extension_settings = [];

		$user = wp_get_current_user();

		if ( ! is_user_logged_in() ) {
			// If the user is a guest and has an account on the merchant site, load data
			// from there to check if we need to verify their email on WooPay later.
			$user_by_email = get_user_by( 'email', $email );

			if ( false !== $user_by_email ) {
				$user = $user_by_email;
			}
		}

		// Points and Rewards.
		if ( in_array( self::POINTS_AND_REWARDS_PLUGIN, $enabled_adapted_extensions, true ) ) {
			$points_and_rewards_data = self::get_points_and_rewards_data( $user );

			if ( null !== $points_and_rewards_data ) {
				$extension_settings[ self::POINTS_AND_REWARDS_API ] = $points_and_rewards_data;
			}
		}

		if ( in_array( self::GIFT_CARDS_API, $enabled_adapted_extensions, true ) ) {
			$gift_cards_data = self::get_gift_cards_data( $user );

			if ( null !== $gift_cards_data ) {
				$extension_settings[ self::GIFT_CARDS_API ] = $gift_cards_data;
			}
		}

		return $extension_settings;
	}

	/**
	 * Get Points and Rewards settings for WooPay.
	 *
	 * @param \WP_User $user The user the data will be loaded.
	 *
	 * @return array|null The Points and Rewards script data if installed.
	 */
	public function get_points_and_rewards_data( $user ) {
		if (
			empty( $this->registered_integrations[ self::POINTS_AND_REWARDS_API ] ) ||
			! class_exists( 'WC_Points_Rewards_Manager' ) ||
			! method_exists( 'WC_Points_Rewards_Manager', 'get_users_points' )
		) {
			return null;
		}

		$points_and_rewards_script_data = $this->registered_integrations[ self::POINTS_AND_REWARDS_API ]->get_script_data();

		list( $points, $monetary_value ) = explode( ':', get_option( 'wc_points_rewards_redeem_points_ratio', '' ) );

		$points         = floatval( $points );
		$monetary_value = floatval( $monetary_value );

		$points_and_rewards_script_data['points_ratio'] = [
			'points'         => $points,
			'monetary_value' => $monetary_value,
		];

		/**
		 * Check if the user has points to show the verify email alert.
		 *
		 * @psalm-suppress UndefinedClass
		 */
		$available_points_for_user = \WC_Points_Rewards_Manager::get_users_points( $user->ID );

		if ( $available_points_for_user > 0 && $available_points_for_user > $points_and_rewards_script_data['minimum_points_amount'] ) {
			// Only ask the user to verify email if they have available points.
			$points_and_rewards_script_data['should_verify_email'] = ! is_user_logged_in();
			$points_and_rewards_script_data['points_available']    = $available_points_for_user;
		}

		return $points_and_rewards_script_data;
	}

	/**
	 * Get Gift Cards settings for WooPay.
	 *
	 * @param \WP_User $user The user the data will be loaded.
	 *
	 * @return array|null The Gift Cards script data if installed.
	 */
	public function get_gift_cards_data( $user ) {
		if (
			empty( $this->registered_integrations[ self::GIFT_CARDS_BLOCKS ] ) ||
			! function_exists( 'WC_GC' ) ||
			! property_exists( WC_GC(), 'account' ) ||
			! method_exists( WC_GC()->account, 'get_active_giftcards' )
		) {
			return null;
		}

		$gift_cards_script_data                        = $this->registered_integrations[ self::GIFT_CARDS_BLOCKS ]->get_script_data();
		$gift_cards_script_data['should_verify_email'] = false;

		if ( ! is_user_logged_in() ) {
			// Verify if the user has Gift Card balance to ask them to verify email on WooPay.
			$gift_cards = WC_GC()->account->get_active_giftcards( $user->ID );
			$balance    = 0;

			foreach ( $gift_cards as $giftcard_data ) {
				$balance += (float) $giftcard_data->get_balance();
			}

			if ( $balance > 0 ) {
				$gift_cards_script_data['should_verify_email'] = true;
			}
		}

		return $gift_cards_script_data;
	}

	/**
	 * The custom data from plugins to be used on WooPay,
	 * it's not an adapted extension because it doesn't
	 * use the email verification integration.
	 *
	 * @return array The custom data.
	 */
	public function get_extension_data() {
		$extension_data = [];

		if ( defined( 'WOOCOMMERCE_MULTICURRENCY_VERSION' ) ) {
			$extension_data[ 'woocommerce-multicurrency' ] = [
				'currency' => get_woocommerce_currency(),
			];
		}

		if ( $this->is_affiliate_for_woocommerce_enabled() ) {
			/**
			 * @psalm-suppress UndefinedFunction
			 */
			$extension_data[ 'affiliate-for-woocommerce' ] = [
				'affiliate-user' => afwc_get_referrer_id(),
			];
		}

		if ( $this->is_automate_woo_referrals_enabled() ) {
			$advocate_id = $this->get_automate_woo_advocate_id_from_cookie();

			$extension_data[ 'automatewoo-referrals' ] = [
				'advocate_id' => $advocate_id,
			];
		}

		return $extension_data;
	}

	/**
	 * Update order extension data after finishing
	 * an order on WooPay, this usually is needed
	 * for extensions which uses cookies when an
	 * order is finished.
	 *
	 * @param int $order_id The successful WooPay order.
	 */
	public function update_order_extension_data( $order_id ) {
		if ( ! empty( $_GET['affiliate'] ) && // phpcs:ignore WordPress.Security.NonceVerification
			$this->is_affiliate_for_woocommerce_enabled()
		) {
			$affiliate_id = (int) wc_clean( wp_unslash( $_GET['affiliate'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedClass
			 */
			$affiliate_api = \AFWC_API::get_instance();
			$affiliate_api->track_conversion( $order_id, $affiliate_id, '', [ 'is_affiliate_eligible' => true ] );
		}
	}

	/**
	 * Get WC Blocks registered integrations.
	 *
	 * @param IntegrationInterface $integration An instance of IntegrationInterface.
	 *
	 * @return boolean True means registered successfully.
	 */
	public function register( IntegrationInterface $integration ) {
		$name = $integration->get_name();

		if ( self::GIFT_CARDS_BLOCKS === $name || self::POINTS_AND_REWARDS_API === $name ) {
			$this->registered_integrations[ $name ] = $integration;
		}

		return true;
	}

	/**
	 * Check if Affiliate for WooCommerce is enabled and
	 * its functions used on WCPay are available.
	 *
	 * @return boolean
	 */
	public function is_affiliate_for_woocommerce_enabled() {
		return defined( 'AFWC_PLUGIN_FILE' ) &&
			function_exists( 'afwc_get_referrer_id' ) &&
			class_exists( 'AFWC_API' ) &&
			method_exists( 'AFWC_API', 'get_instance' ) &&
			method_exists( 'AFWC_API', 'track_conversion' );
	}

	/**
	 * Check if Automate Woo Referrals is enabled and
	 * its functions used on WCPay are available.
	 *
	 * @psalm-suppress UndefinedClass
	 * @psalm-suppress UndefinedFunction
	 *
	 * @return boolean
	 */
	private function is_automate_woo_referrals_enabled() {
		return function_exists( 'AW_Referrals' ) &&
		method_exists( AW_Referrals(), 'options' ) &&
		AW_Referrals()->options()->type === 'link' &&
		class_exists( '\AutomateWoo\Referrals\Referral_Manager' ) &&
		method_exists( \AutomateWoo\Referrals\Referral_Manager::class, 'get_advocate_key_from_cookie' );
	}

	/**
	 * Get AutomateWoo advocate id from cookie.
	 *
	 * @psalm-suppress UndefinedClass
	 *
	 * @return string|null
	 */
	private function get_automate_woo_advocate_id_from_cookie() {
		$advocate_from_key_cookie = \AutomateWoo\Referrals\Referral_Manager::get_advocate_key_from_cookie();
		return $advocate_from_key_cookie ? $advocate_from_key_cookie->get_advocate_id() : null;
	}
}
