/**
 * External dependencies
 */
import { __, _n } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isInTestMode, getPaymentSettingsUrl } from 'utils';

// The topics (i.e. pages) that have test mode notices.
export const topics = {
	transactions: __(
		'Viewing test transactions. To view live transactions, disable test mode in WooCommerce Payments settings.',
		'woocommerce-payments'
	),
	paymentDetails: __( 'Test payment:', 'woocommerce-payments' ),
	deposits: __(
		'Viewing test deposits. To view live deposits, disable test mode in WooCommerce Payments settings.',
		'woocommerce-payments'
	),
	depositDetails: __( 'Test deposit:', 'woocommerce-payments' ),
	disputes: __(
		'Viewing test disputes. To view live disputes, disable test mode in WooCommerce Payments settings.',
		'woocommerce-payments'
	),
	disputeDetails: __( 'Test dispute:', 'woocommerce-payments' ),
};

// These are all the topics used for details pages where the notice is slightly different.
const detailsTopics = [
	topics.paymentDetails,
	topics.disputeDetails,
	topics.depositDetails,
];

/**
 * Returns an <a> tag with the href attribute set to the Payments settings
 * page, and the provided text.
 *
 * @param {string} topic The notice message topic.
 *
 * @returns {*} An HTML <a> component with a link to wcpay settings page.
 */
export const getPaymentsSettingsUrlComponent = () => {
	return (
		<a href={ getPaymentSettingsUrl() }>
			{ __(
				'View WooCommerce Payments settings.',
				'woocommerce-payments'
			) }
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
	return _n(
		'WooCommerce Payments was in test mode when this order was placed.',
		'WooCommerce Payments was in test mode when these orders were placed.',
		topics.depositDetails === topic ? 2 : 1,
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
export const getNoticeMessage = ( topic ) => {
	const urlComponent = getPaymentsSettingsUrlComponent();

	if ( detailsTopics.includes( topic ) ) {
		return (
			<span>
				<b>{ topic }</b> { getTopicDetails( topic ) } { urlComponent }
			</span>
		);
	}

	return (
		<span>
			{ topic } { urlComponent }
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
export const getNotice = ( topic ) => {
	return (
		<Notice
			className="wcpay-test-mode-notice"
			status="warning"
			isDismissible={ false }
		>
			{ getNoticeMessage( topic ) }
		</Notice>
	);
};

/**
 * Adds a test notice that will be displayed when test mode is enabled.
 *
 * @param {Function | React.Component} Component The component to add the notice to.
 * @param {string} pageTopic                   The topic for this notice, e.g. 'transactions'.
 *
 * @returns {Function} The wrapped component, including a notice when applicable.
 */
export const withTestNotice = ( Component, pageTopic ) => {
	return ( props, ownProps ) => (
		<div>
			{ isInTestMode() ? getNotice( pageTopic ) : null }
			{ Component( props, ownProps ) }
		</div>
	);
};
