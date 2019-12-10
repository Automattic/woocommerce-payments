/** @format **/

/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Link } from '@woocommerce/components';
import Gridicon from 'gridicons';
import { capitalize } from 'lodash';

const currency = new Currency();

const headers = [
	{ key: 'amount', label: 'Amount' },
	{ key: 'status', label: 'Status' },
	{ key: 'reason', label: 'Reason' },
	{ key: 'created', label: 'Disputed On' },
	{ key: 'dueBy', label: 'Respond By' },
];

export const DisputesList = ( props ) => {
	const { disputes, showPlaceholder } = props;
	const disputesData = disputes.data || [];

	const rows = disputesData.map( ( dispute ) => {
		const evidenceLink = dispute.status.indexOf( 'needs_response' ) === -1 ? null : (
			<Link href={ `?page=wc-admin&path=/payments/disputes/evidence&id=${ dispute.id }` }>
				<Gridicon icon="reply" size={ 18 } />
			</Link>
		);

		const data = {
			amount: { value: dispute.amount / 100, display: currency.formatCurrency( dispute.amount / 100 ) },
			status: {
				value: dispute.status,
				display: <>{ evidenceLink } <code>{ capitalize( dispute.status.replace( /_/g, ' ' ) ) }</code></>,
			},
			reason: { value: dispute.reason, display: capitalize( dispute.reason.replace( /_/g, ' ' ) ) },
			created: { value: dispute.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( dispute.created * 1000 ) ) },
			dueBy: {
				value: dispute.evidence_details.due_by * 1000,
				display: dateI18n( 'M j, Y / g:iA', moment( dispute.evidence_details.due_by * 1000 ) ),
			},
		};

		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	return (
		<TableCard
			title="Disputes"
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
