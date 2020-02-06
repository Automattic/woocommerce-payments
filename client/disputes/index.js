/** @format **/

/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import OrderLink from '../components/order-link';
import Chip from '../components/chip';
import { displayStatus, displayReason } from './strings';

const currency = new Currency();

const headers = [
	{ key: 'amount', label: 'Amount' },
	{ key: 'status', label: 'Status' },
	{ key: 'reason', label: 'Reason' },
	{ key: 'order', label: 'Order #' },
	{ key: 'created', label: 'Disputed On' },
	{ key: 'dueBy', label: 'Respond By' },
];

export const DisputesList = ( props ) => {
	const { disputes, showPlaceholder } = props;
	const disputesData = disputes.data || [];

	const rows = disputesData.map( ( dispute ) => {
		let status = displayStatus[ dispute.status ] || {};
		status = <Chip message={ status.message } type={ status.type } />;
		if ( dispute.status.indexOf( 'needs_response' ) >= 0 ) {
			const detailsUrl = addQueryArgs(
				'admin.php',
				{
					page: 'wc-admin',
					path: '/payments/disputes/evidence',
					id: dispute.id,
				}
			);
			status = <Link href={ detailsUrl }>{ status }</Link>;
		}

		const order = dispute.order ? {
				value: dispute.order.number,
				display: <OrderLink order={ dispute.order } />,
			} : null;

		const data = {
			amount: { value: dispute.amount / 100, display: currency.formatCurrency( dispute.amount / 100 ) },
			status: { value: dispute.status, display: status },
			reason: { value: dispute.reason, display: displayReason[ dispute.reason ] || dispute.reason },
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
