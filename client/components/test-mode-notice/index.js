/**
 * External dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isInTestMode, getPaymentSettingsUrl } from 'utils';

// The topics (i.e. pages) that have test mode notices.
export const topics = {
	overview: sprintf(
		/* translators: %s: WooPayments */
		__( '%s is in test mode.', 'woocommerce-payments' ),
		'WooPayments'
	),
	transactions: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test transactions. To view live transactions, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	paymentDetails: __( 'Test payment:', 'woocommerce-payments' ),
	deposits: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test deposits. To view live deposits, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	depositDetails: __( 'Test deposit:', 'woocommerce-payments' ),
	disputes: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test disputes. To view live disputes, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	disputeDetails: __( 'Test dispute:', 'woocommerce-payments' ),
	documents: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test documents. To view live documents, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	documentDetails: __( 'Test document:', 'woocommerce-payments' ),
	loans: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test loans. To view live loans, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	authorizations: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test authorizations. To view live authorizations, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	riskReviewTransactions: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test on review transactions. To view live on review transactions, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
	blockedTransactions: sprintf(
		/* translators: %s: WooPayments */
		__(
			'Viewing test blocked transactions. To view live blocked transactions, disable test mode in %s settings.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
};

// These are all the topics used for details pages where the notice is slightly different.
const detailsTopics = [
	topics.paymentDetails,
	topics.disputeDetails,
	topics.depositDetails,
	topics.documentDetails,
];

/**
 * Returns an <a> tag with the href attribute set to the Payments settings
 * page, and the provided text.
 *
 * @param {string} topic The notice message topic.
 *
 * @return {*} An HTML <a> component with a link to wcpay settings page.
 */
export const getPaymentsSettingsUrlComponent = () => {
	return (
		<a href={ getPaymentSettingsUrl() }>
			{ sprintf(
				/* translators: %s: WooPayments */
				__( 'View %s settings', 'woocommerce-payments' ),
				'WooPayments'
			) }
		</a>
	);
};

/**
 * Returns notice details depending on the topic provided.
 *
 * @param {string} topic The notice message topic.
 *
 * @return {string} The specific details the notice is supposed to contain.
 */
export const getTopicDetails = ( topic ) => {
	return sprintf(
		/* translators: %s: WooPayments */
		_n(
			'%s was in test mode when this order was placed.',
			'%s was in test mode when these orders were placed.',
			topics.depositDetails === topic ? 2 : 1,
			'woocommerce-payments'
		),
		'WooPayments'
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
 * @return {string} The correct notice message.
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

export const TestModeNotice = ( { topic } ) => {
	if ( isInTestMode() ) {
		return (
			<Notice
				className="wcpay-test-mode-notice"
				status="warning"
				isDismissible={ false }
			>
				{ getNoticeMessage( topic ) }
			</Notice>
		);
	}
	return <></>;
};
