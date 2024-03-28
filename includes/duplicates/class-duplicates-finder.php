<?php
/**
 * Class Duplicates_Finder
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Duplicates;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class DuplicatesFinder
 *
 * This class is responsible for finding and handling duplicates in the WooCommerce Payments plugin.
 * It provides methods to search for duplicate entries and perform actions on them.
 */
class Duplicates_Finder {

	/**
	 * Possible keys to check for.
	 *
	 * @var array
	 */
	public static $gateway_ids = [
		'apple_pay',
		'applepay',
		'google_pay',
		'googlepay',
		'affirm',
		'afterpay',
		'clearpay',
		'klarna',
		'credit_card',
		'credicard',
		'cc',
		'bancontact',
		'ideal',
	];

	/**
	 * Find duplicates.
	 *
	 * @param array $gateways All enabled gateways.
	 * @return array Duplicated gateways.
	 */
	private function find_duplicates( $gateways ) {
		// Use associative array for counting occurrences.
		$counter                    = [];
		$duplicated_payment_methods = [];

		$gateway_ids = [
			'apple_pay',
			'applepay',
			'google_pay',
			'googlepay',
			'affirm',
			'afterpay',
			'clearpay',
			'klarna',
			'credit_card',
			'credicard',
			'cc',
			'bancontact',
			'ideal',
		];

		// Only loop through gateways once.
		foreach ( $gateways as $gateway ) {
			foreach ( $gateway_ids as $keyword ) {
				if ( strpos( $gateway->id, $keyword ) !== false ) {
					// Increment counter or initialize if not exists.
					if ( isset( $counter[ $keyword ] ) ) {
						$counter[ $keyword ]++;
					} else {
						$counter[ $keyword ] = 1;
					}

					// If more than one occurrence, add to duplicates.
					if ( $counter[ $keyword ] > 1 ) {
						$duplicated_payment_methods[] = $gateway->title; // Use keys to prevent duplicates.
					}
					break; // Stop searching once a match is found for this gateway.
				}
			}
		}

		// Return duplicated gateway titles.
		return array_values( $duplicated_payment_methods );
	}
}
