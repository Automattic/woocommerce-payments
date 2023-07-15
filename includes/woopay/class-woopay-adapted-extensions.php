<?php
/**
 * WooPay
 *
 * @package WCPay\WooPay
 */

namespace WCPay\WooPay;

/**
 * WooPay
 */
class WooPay_Adapted_Extensions {
	const POINTS_AND_REWARDS_PLUGIN = 'woocommerce-points-and-rewards';
	const POINTS_AND_REWARDS_API    = 'points-and-rewards';
	const GIFT_CARDS                = 'woocommerce-gift-cards';

	/**
	 * Get WooPay adapted extensions settings and extra data needed on WooPay.
	 *
	 * @param string $email The user email the data will be loaded.
	 */
	public static function get_adapted_extensions_data( $email ) {
		$enabled_adapted_extensions = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );
		$extension_settings         = [];

		if ( count( $enabled_adapted_extensions ) === 0 ) {
			return null;
		}

		$user = wp_get_current_user();
		// WooPay will only ask to verify email if it's a guest user or doesn't match logged in user.
		$email_and_merchant_login_match = $email === $user->user_email;

		if ( ! $email_and_merchant_login_match ) {
			// If the user is guest and has a merchant account, load data from there.
			$user_by_email = get_user_by( 'email', $email );
			$user          = $user_by_email ?? $user;
		}

		// Points and Rewards.
		if ( in_array( self::POINTS_AND_REWARDS_PLUGIN, $enabled_adapted_extensions, true ) ) {
			$extension_settings[ self::POINTS_AND_REWARDS_API ] = self::get_points_and_rewards_data( $user, $email_and_merchant_login_match );
		}

		if ( in_array( self::GIFT_CARDS, $enabled_adapted_extensions, true ) ) {
			$extension_settings[ self::GIFT_CARDS ] = self::get_gift_cards_data( $user, $email_and_merchant_login_match );
		}

		return $extension_settings;
	}

	/**
	 * Get Points and Rewards settings for WooPay.
	 *
	 * @param WP_User $user The user the data will be loaded.
	 * @param bool    $email_and_merchant_login_match If the email and current logged in user match.
	 */
	private static function get_points_and_rewards_data( $user, $email_and_merchant_login_match ) {
		if (
			! class_exists( 'WC_Points_Rewards_Manager' ) ||
			! class_exists( 'WC_Points_Rewards_Integration' ) ||
			! method_exists( 'WC_Points_Rewards_Manager', 'get_users_points' ) ||
			! method_exists( 'WC_Points_Rewards_Integration', 'get_script_data' )
		) {
			return null;
		}

		$points_and_rewards_integration = new \WC_Points_Rewards_Integration();
		$points_and_rewards_script_data = $points_and_rewards_integration->get_script_data();

		list( $points, $monetary_value ) = explode( ':', get_option( 'wc_points_rewards_redeem_points_ratio', '' ) );

		$points         = floatval( $points );
		$monetary_value = floatval( $monetary_value );

		$points_and_rewards_script_data['points_ratio'] = [
			'points'         => $points,
			'monetary_value' => $monetary_value,
		];

		// This data will only be loaded on WooPay if the email is verified.
		$available_points_for_user = \WC_Points_Rewards_Manager::get_users_points( $user->ID );

		if ( $available_points_for_user > 0 && $available_points_for_user > $points_and_rewards_script_data['minimum_points_amount'] ) {
			// Only ask the user to verify email if they have available points.
			$points_and_rewards_script_data['should_verify_email'] = ! $email_and_merchant_login_match;
			$points_and_rewards_script_data['points_available']    = $available_points_for_user;
		}

		return $points_and_rewards_script_data;
	}

	/**
	 * Get Gift Cards settings for WooPay.
	 *
	 * @param WP_User $user The user the data will be loaded.
	 * @param bool    $email_and_merchant_login_match If the email and current logged in user match.
	 */
	private static function get_gift_cards_data( $user, $email_and_merchant_login_match ) {
		if (
			! function_exists( 'WC_GC' ) ||
			! class_exists( 'WC_GC_Checkout_Blocks_Integration' ) ||
			! method_exists( 'WC_GC_Checkout_Blocks_Integration', 'get_script_data' )
		) {
			return null;
		}

		$gift_cards_integration = new \WC_GC_Checkout_Blocks_Integration();
		$gift_cards_script_data = $gift_cards_integration->get_script_data();

		$gift_cards_balance = WC_GC()->account->get_balance( $user->ID );

		if ( ! $email_and_merchant_login_match && $gift_cards_balance > 0 ) {
			$gift_cards_script_data['should_verify_email'] = ! $email_and_merchant_login_match;
		}

		return $gift_cards_script_data;
	}
}
