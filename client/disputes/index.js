/** @format **/

/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Link } from '@woocommerce/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies.
 */
import OrderLink from '../components/order-link';
import DisputeStatusChip from '../components/dispute-status-chip';
import { reasons } from './strings';
import { formatStringValue } from '../util';

const currency = new Currency();

const headers = [
	{ key: 'details', label: '', required: true, cellClassName: 'info-button' },
	{ key: 'amount', label: __( 'Amount', 'woocommerce-payments' ) },
	{ key: 'status', label: __( 'Status', 'woocommerce-payments' ) },
	{ key: 'reason', label: __( 'Reason', 'woocommerce-payments' ) },
	{ key: 'order', label: __( 'Order #', 'woocommerce-payments' ) },
	{ key: 'created', label: __( 'Disputed On', 'woocommerce-payments' ) },
	{ key: 'dueBy', label: __( 'Respond By', 'woocommerce-payments' ) },
];

export const DisputesList = ( props ) => {
	const { disputes, showPlaceholder } = props;
	const disputesData = disputes.data || [];

	const rows = disputesData.map( ( dispute ) => {
		const order = dispute.order ? {
			value: dispute.order.number,
			display: <OrderLink order={ dispute.order } />,
		} : null;

		const detailsUrl = addQueryArgs(
			'admin.php',
			{
				page: 'wc-admin',
				path: '/payments/disputes/details',
				id: dispute.id,
			}
		);
		const detailsLink = (
			<Link href={ detailsUrl } >
				<Gridicon icon="info-outline" size={ 18 } />
			</Link>
		);

		const reasonMapping = reasons[ dispute.reason ];
		const reasonDisplay = reasonMapping ? reasonMapping.display : formatStringValue( dispute.reason );

		const data = {
			amount: { value: dispute.amount / 100, display: currency.formatCurrency( dispute.amount / 100 ) },
			status: { value: dispute.status, display: <DisputeStatusChip status={ dispute.status } /> },
			reason: { value: dispute.reason, display: reasonDisplay },
			created: { value: dispute.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( dispute.created * 1000 ) ) },
			dueBy: {
				value: dispute.evidence_details.due_by * 1000,
				display: dateI18n( 'M j, Y / g:iA', moment( dispute.evidence_details.due_by * 1000 ) ),
			},
			order,
			details: { value: dispute.id, display: detailsLink },
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
