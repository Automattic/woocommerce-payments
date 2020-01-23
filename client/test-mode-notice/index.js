/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isInTestMode, getPaymentSettingsUrl } from '../util';

// The topics (i.e. pages) that have test mode notices.
export const topics = {
	transactions: __(
		'Viewing test transactions. To view live transactions, disable test mode in WooCommerce Payments',
		'woocommerce-payments'
	),
	paymentDetails: __( 'Test payment:', 'woocommerce-payments' ),
	deposits: __(
		'Viewing test deposits. To view live deposits, disable test mode in WooCommerce Payments',
		'woocommerce-payments'
	),
	depositDetails: __( 'Test deposit:', 'woocommerce-payments' ),
	disputes: __(
		'Viewing test disputes. To view live disputes, disable test mode in WooCommerce Payments',
		'woocommerce-payments'
	),
	disputeEvidence: __( 'Test dispute:', 'woocommerce-payments' ),
};

// These are all the topics used for details pages where the notice is slightly different.
const detailsTopics = [
	topics.paymentDetails,
	topics.disputeEvidence,
	topics.depositDetails,
];

/**
 * Returns the text that should be used for the Payments settings url, based
 * on what the provided topic is.
 *
 * @param {string} topic The notice message topic.
 *
 * @returns {string} The string to use for the Payments settings URL.
 */
const getPaymentsSettingsUrlContent = ( topic ) => {
	if ( detailsTopics.includes( topic ) ) {
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
 * Returns notice details depending on the topic provided.
 *
 * @param {string} topic The notice message topic.
 *
 * @returns {string} The specific details the notice is supposed to contain.
 */
export const getTopicDetails = ( topic ) => {
	if ( topics.depositDetails === topic ) {
		return __(
			'WooCommerce Payments was in test mode when these orders were placed.',
			'woocommerce-payments'
		);
	}

	return __(
		'WooCommerce Payments was in test mode when this order was placed.',
		'woocommerce-payments'
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
	const urlComponent = getPaymentsSettingsUrlComponent( topic );

	if ( detailsTopics.includes( topic ) ) {
		return (
			<span><b>{ topic }</b> { getTopicDetails( topic ) } { urlComponent }</span>
		);
	}

	return (
		<span>{ topic } { urlComponent }</span>
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
		<Notice className={ 'wcpay-test-mode-notice' } status="warning" isDismissible={ false }>
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
			{ Component( props, ownProps ) }
		</div>
	);
};
