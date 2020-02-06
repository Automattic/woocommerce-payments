/** @format **/
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

// Mapping of dispute status to display string.
export const displayStatus = {
	warning_needs_response: __( 'Inquiry: Needs response', 'woocommerce-payments' ),
	warning_under_review: __( 'Inquiry: Under review', 'woocommerce-payments' ),
	warning_closed: __( 'Inquiry: Closed', 'woocommerce-payments' ),
	needs_response: __( 'Needs response', 'woocommerce-payments' ),
	under_review: __( 'Under review', 'woocommerce-payments' ),
	charge_refunded: __( 'Charge refunded', 'woocommerce-payments' ),
	won: __( 'Won', 'woocommerce-payments' ),
	lost: __( 'Lost', 'woocommerce-payments' ),
};

// Mapping of dispute reason to display string.
export const displayReason = {
	bank_cannot_process: __( 'Bank cannot process', 'woocommerce-payments' ),
	check_returned: __( 'Check returned', 'woocommerce-payments' ),
	credit_not_processed: __( 'Credit not processed', 'woocommerce-payments' ),
	customer_initiated: __( 'Customer initiated', 'woocommerce-payments' ),
	debit_not_authorized: __( 'Debit not authorized', 'woocommerce-payments' ),
	duplicate: __( 'Duplicate', 'woocommerce-payments' ),
	fraudulent: __( 'Fraudulent', 'woocommerce-payments' ),
	general: __( 'General', 'woocommerce-payments' ),
	incorrect_account_details: __( 'Incorrect account details', 'woocommerce-payments' ),
	insufficient_funds: __( 'Insufficient funds', 'woocommerce-payments' ),
	product_not_received: __( 'Product not received', 'woocommerce-payments' ),
	product_unacceptable: __( 'Product unacceptable', 'woocommerce-payments' ),
	subscription_canceled: __( 'Subscription canceled', 'woocommerce-payments' ),
	unrecognized: __( 'Unrecognized', 'woocommerce-payments' ),
};
