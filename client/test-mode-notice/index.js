/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isInTestMode, getPaymentSettingsUrl } from '../util';

export const topics = {
	paymentDetails: 'payment-details',
	transactions: 'transactions',
	deposits: 'deposits',
	disputes: 'disputes',
};

/**
 * Returns the text that should be used for the Payments settings url, based
 * on what the provided topic is.
 *
 * @param {string} topic The notice message topic.
 *
 * @returns {string} The string to use for the Payments settings URL.
 */
const getPaymentsSettingsUrlContent = ( topic ) => {
	if ( topics.paymentDetails === topic ) {
		return __( 'View WooCommerce Payments settings.', 'woocommerce-payments' );
	}

	return __( 'settings.', 'woocommerce-payments' );
};

/**
 * Returns an <a> tag with the href attribute set to the Payments settings
 * page, and the provided text.
 *
 * @param {string} topic The notice message topic.
 *
 * @returns {*} An HTML <a> component with a link to wcpay settings page.
 */
const getPaymentsSettingsUrlComponent = ( topic ) => {
	return (
		<a href={ getPaymentSettingsUrl() }>
			{ getPaymentsSettingsUrlContent( topic ) }
		</a>
	);
};

/**
 * Returns the correct notice message wrapped in a span for a given topic.
 *
 * The message is wrapped in a span to make it easier to apply styling to
 * different parts of the text, i.e. to include multiple HTML elements.
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
				{
					__( 'WooCommerce Payments was in test mode when this order was placed.', 'woocommerce-payments' )
				} {
					getPaymentsSettingsUrlComponent( topic )
				}
			</span>
		);
	}

	return (
		<span>
		{
			sprintf(
				__(
					'Viewing test %s. To view live %s, disable test mode in WooCommerce Payments',
					'woocommerce-payments'
				),
				topic,
				topic
			)
		} {
			getPaymentsSettingsUrlComponent( topic )
		}
		</span>
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
	return (
		<Notice status="warning" isDismissible={ false }>
			<p>{ getNoticeMessage( topic ) }</p>
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
