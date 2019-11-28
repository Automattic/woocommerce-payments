/** @format **/

/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { formatCurrency } from '@woocommerce/currency';
import { TableCard } from '@woocommerce/components';
import { capitalize } from 'lodash';

const headers = [
	{ key: 'amount', label: 'Amount' },
	{ key: 'status', label: 'Status' },
	{ key: 'reason', label: 'Reason' },
	{ key: 'created', label: 'Disputed On' },
	{ key: 'due_by', label: 'Respond By' },
	{ key: 'id', label: 'ID' }, // TODO remove
];

export const DisputesList = ( props ) => {
	const { disputes, showPlaceholder } = props;
	const disputesData = disputes.data || [];

	const rows = disputesData.map( ( dispute ) => {
		// Map dispute into table row.
		const data = {
			amount: { value: dispute.amount / 100, display: formatCurrency( dispute.amount / 100 ) },
			status: { value: dispute.status, display: <code>{ capitalize( dispute.status.replace( /_/g, ' ' ) ) }</code> },
			reason: { value: dispute.reason, display: capitalize( dispute.reason.replace( /_/g, ' ' ) ) },
			created: { value: dispute.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( dispute.created * 1000 ) ) },
			due_by: { value: dispute.evidence_details.due_by * 1000, display: dateI18n( 'M j, Y / g:iA', moment( dispute.evidence_details.due_by * 1000 ) ) },
			id: { value: dispute.id, display: dispute.id, hiddenByDefault: true }, // TODO remove
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
	}
	useEffect( () => { fetchDisputes() }, [] );

	return (
		<DisputesList
			disputes={ disputes }
			showPlaceholder={ loading }
		/>
	);
};
