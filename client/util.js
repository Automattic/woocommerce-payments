
/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import 'payments-api/payments-data-store'; // For wcpaySettings.

export const topics = {
	paymentDetails: 'payment-details',
	transactions: 'transactions',
	deposits: 'deposits',
	disputes: 'disputes',
};

export const isInTestMode = ( fallback = false ) => {
	if ( 'undefined' === typeof wcpaySettings ) {
		return fallback;
	}
	return '1' === wcpaySettings.testMode || fallback;
};

/**
 * Returns an <a> tag with the href attribute set to the Payments settings
 * page, and the provided text.
 *
 * @param {string} urlText The text to show in the link.
 *
 * @returns {*} An HTML <a> component with a link to wcpay settings page.
 */
const getPaymentsSettingsUrlComponent = ( urlText ) => {
	const settingsUrl = addQueryArgs(
		'admin.php',
		{
			page: 'wc-settings',
			tab: 'checkout',
			section: 'woocommerce_payments',
		}
	);
	return (
		<a href={ settingsUrl }>
			{ __( urlText, 'woocommerce-payments' ) }
		</a>
	);
};

/**
 * Returns the correct notice message for a given topic.
 *
 * @param {string} topic The notice message topic.
 *
 * @returns {string} The correct notice message.
 */
const getNoticeMessage = ( topic ) => {
	if ( topics.paymentDetails === topic ) {
		return (
			<span>
				<b>{ __( 'Test payment: ', 'woocommerce-payments' ) }</b>
				{ __( 'WooCommerce Payments was in test mode when this order was placed.', 'woocommerce-payments' ) }
			</span>
		);
	}
	return sprintf(
		__(
			'Viewing test %s. To view live %s, disable test mode in WooCommerce Payments',
			'woocommerce-payments'
		),
		topic,
		topic
	);
};

/**
 * Returns a Notice element with the appropriate message based on the topic provided.
 *
 * @param {string} topic The notice topic, also represents a page, e.g. 'transactions'.
 *
 * @returns {Notice} The notice element containing the appropriate message.
 */
const getNotice = ( topic ) => {
	const urlText = topics.paymentDetails === topic ? 'View WooPayments settings.' : 'settings.';
	return (
		<Notice status="warning" isDismissible={ false }>
			<p>{ getNoticeMessage( topic ) } { getPaymentsSettingsUrlComponent( urlText ) }</p>
		</Notice>
	);
};

/**
 * Adds a test notice that will be displayed when test mode is enabled.
 *
 * @param {function|React.Component} Component The component to add the notice to.
 * @param {string} pageTopic                   The topic for this notice, e.g. 'transactions'.
 *
 * @returns {function} The wrapped component, including a notice when applicable.
 */
export const withTestNotice = ( Component, pageTopic ) => {
	return ( props, ownProps ) => (
		<div>
			{ isInTestMode() ? getNotice( pageTopic ) : null }
			{ isInTestMode() ? <br /> : null }
			{ Component( props, ownProps ) }
		</div>
	);
};
