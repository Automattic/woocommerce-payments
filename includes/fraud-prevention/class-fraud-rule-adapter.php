<?php
/**
 * The rule adapter class responsible for converting UI rules to server rulesets, and vice versa.
 *
 * @package WCPay\Fraud_Prevention\Models
 */

namespace WCPay\Fraud_Prevention\Models;

use ReflectionClass;
use ReflectionMethod;
use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Fraud_Prevention\Rules\Rule_Address_Mismatch;
use WCPay\Fraud_Prevention\Rules\Rule_Avs_Mismatch;
use WCPay\Fraud_Prevention\Rules\Rule_Cvc_Verification;
use WCPay\Fraud_Prevention\Rules\Rule_International_Billing_Address;
use WCPay\Fraud_Prevention\Rules\Rule_International_Ip_Address;
use WCPay\Fraud_Prevention\Rules\Rule_Order_Items_Threshold;
use WCPay\Fraud_Prevention\Rules\Rule_Order_Velocity;
use WCPay\Fraud_Prevention\Rules\Rule_Purchase_Price_Threshold;
use WCPay\Logger;

/**
 * Check model.
 */
class Fraud_Rule_Adapter {
	/**
	 * Maps the rule keys with rule classes.
	 *
	 * @var array
	 */
	private static $rule_classes = [
		Fraud_Risk_Tools::RULE_ADDRESS_MISMATCH         => Rule_Address_Mismatch::class,
		Fraud_Risk_Tools::RULE_AVS_MISMATCH             => Rule_Avs_Mismatch::class,
		Fraud_Risk_Tools::RULE_CVC_VERIFICATION         => Rule_Cvc_Verification::class,
		Fraud_Risk_Tools::RULE_INTERNATIONAL_BILLING_ADDRESS => Rule_International_Billing_Address::class,
		Fraud_Risk_Tools::RULE_INTERNATIONAL_IP_ADDRESS => Rule_International_Ip_Address::class,
		Fraud_Risk_Tools::RULE_ORDER_ITEMS_THRESHOLD    => Rule_Order_Items_Threshold::class,
		Fraud_Risk_Tools::RULE_ORDER_VELOCITY           => Rule_Order_Velocity::class,
		Fraud_Risk_Tools::RULE_PURCHASE_PRICE_THRESHOLD => Rule_Purchase_Price_Threshold::class,
	];

	/**
	 * Builds ruleset configuration from fraud level settings for sending to the server.
	 *
	 * @param   array $protection_settings  The UI settings array to generate rules from.
	 *
	 * @return  array|bool                  The generated structure for the rule engine, or false when encoding fails.
	 */
	public static function to_server_ruleset( $protection_settings ) {
		$rule_configuration = [];
		foreach ( $protection_settings as $key => $setting ) {
			if ( $setting['enabled'] ) {
				$class                = new ReflectionClass( self::$rule_classes[ $key ] );
				$instance             = $class->newInstanceArgs( $setting );
				$rule_configuration[] = $instance->get_rule()->to_array();
			}
		}
		return array_filter( $rule_configuration );
	}

	/**
	 * Builds the UI rule configuration from the ruleset config sent from server from server
	 *
	 * @param   array $ruleset_config  Ruleset configuration from server.
	 *
	 * @return  array                  Rule configuration for the UI
	 */
	public static function to_ui_settings( array $ruleset_config ) : array {
		$rule_settings = [];
		foreach ( $ruleset_config as $rule_config ) {
			$rule_object = Rule::from_array( (array) $rule_config );

			$rule_method     = new ReflectionMethod( self::$rule_classes[ $rule_object->key ], 'from_server_rule' );
			$rule_settings   = $rule_method->invoke( null, $rule_object );
			$rule_settings[] = $rule->to_ui_settings();
		}
		return array_merge_recursive( Fraud_Risk_Tools::get_default_protection_settings(), $rule_settings );
	}
}
