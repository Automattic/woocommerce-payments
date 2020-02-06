/** @format **/

/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Link } from '@woocommerce/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies.
 */
import OrderLink from '../components/order-link';

const currency = new Currency();

const headers = [
	{ key: 'amount', label: 'Amount' },
	{ key: 'status', label: 'Status' },
	{ key: 'reason', label: 'Reason' },
	{ key: 'order', label: 'Order #' },
	{ key: 'created', label: 'Disputed On' },
	{ key: 'dueBy', label: 'Respond By' },
];

// Mapping of dispute status to display string.
const displayStatus = {
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
const displayReason = {
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

export const DisputesList = ( props ) => {
	const { disputes, showPlaceholder } = props;
	const disputesData = disputes.data || [];

	const rows = disputesData.map( ( dispute ) => {
		const evidenceLink = dispute.status.indexOf( 'needs_response' ) === -1 ? null : (
			<Link href={ `?page=wc-admin&path=/payments/disputes/evidence&id=${ dispute.id }` }>
				<Gridicon icon="reply" size={ 18 } />
			</Link>
		);

		const order = dispute.order ? {
				value: dispute.order.number,
				display: <OrderLink order={ dispute.order } />,
			} : null;

		const data = {
			amount: { value: dispute.amount / 100, display: currency.formatCurrency( dispute.amount / 100 ) },
			status: {
				value: dispute.status,
				display: <>{ evidenceLink } <code>{ displayStatus[ dispute.status ] }</code></>,
			},
			reason: { value: dispute.reason, display: displayReason[ dispute.reason ] },
			created: { value: dispute.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( dispute.created * 1000 ) ) },
			dueBy: {
				value: dispute.evidence_details.due_by * 1000,
				display: dateI18n( 'M j, Y / g:iA', moment( dispute.evidence_details.due_by * 1000 ) ),
			},
			order,
		};

		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	return (
		<TableCard
			title={ __( 'Disputes', 'woocommerce-payments' ) }
			isLoading={ showPlaceholder }
			rowsPerPage={ 10 }
			totalRows={ 10 }
			headers={ headers }
			rows={ rows }
		/>
	);
};

// Temporary MVP data wrapper
export default () => {
	const [ disputes, setDisputes ] = useState( [] );
	const [ loading, setLoading ] = useState( false );

	const fetchDisputes = async () => {
		setLoading( true );
		setDisputes( await apiFetch( { path: '/wc/v3/payments/disputes' } ) );
		setLoading( false );
	};
	useEffect( () => {
		fetchDisputes();
	}, [] );

	return (
		<DisputesList
			disputes={ disputes }
			showPlaceholder={ loading }
		/>
	);
};
