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

	/**
	 * Get WooPay adapted extensions settings and extra data needed on WooPay.
	 *
	 * @param WP_User $user The user the data will be loaded.
	 * @param bool    $email_and_merchant_login_match If the email and current logged in user match.
	 */
	public static function get_adapted_extensions_data( $user, $email_and_merchant_login_match ) {
		$enabled_adapted_extensions = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );
		$ask_email_verification     = null;
		$extension_settings         = [];

		// Points and Rewards.
		if ( in_array( self::POINTS_AND_REWARDS_PLUGIN, $enabled_adapted_extensions, true ) &&
			class_exists( 'WC_Points_Rewards_Manager' ) &&
			class_exists( 'WC_Points_Rewards_Integration' ) &&
			method_exists( 'WC_Points_Rewards_Manager', 'get_users_points' ) &&
			method_exists( 'WC_Points_Rewards_Integration', 'get_script_data' )
		) {
			$points_and_rewards_integration = new \WC_Points_Rewards_Integration();
			$points_and_rewards_script_data = $points_and_rewards_integration->get_script_data();

			list( $points, $monetary_value ) = explode( ':', get_option( 'wc_points_rewards_redeem_points_ratio', '' ) );

			$points         = floatval( $points );
			$monetary_value = floatval( $monetary_value );

			$points_and_rewards_script_data['points_ratio'] = [
				'points'         => $points,
				'monetary_value' => $monetary_value,
			];

			if ( ! $email_and_merchant_login_match ) {
				// This data will only be loaded on WooPay if the email is verified.
				$available_points_for_user = \WC_Points_Rewards_Manager::get_users_points( $user->ID );

				if ( $available_points_for_user > 0 && $available_points_for_user > $points_and_rewards_script_data['minimum_points_amount'] ) {
					$ask_email_verification                             = self::POINTS_AND_REWARDS_API;
					$points_and_rewards_script_data['points_available'] = $available_points_for_user;
				}
			}

			$extension_settings[ self::POINTS_AND_REWARDS_API ] = $points_and_rewards_script_data;
		}

		return [
			'ask_email_verification'         => $ask_email_verification,
			'email_and_merchant_login_match' => $email_and_merchant_login_match,
			'extension_settings'             => $extension_settings,
		];
	}
}
