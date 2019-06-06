/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { formatCurrency } from '@woocommerce/currency';
import { TableCard } from '@woocommerce/components';

const headers = [
	{ key: 'type', label: 'Type', isLeftAligned: true },
	{ key: 'status', label: 'Status', isLeftAligned: true },
	{ key: 'description', label: 'Description', hiddenByDefault: true, isLeftAligned: true },
	{ key: 'amount', label: 'Amount' },
	{ key: 'fee', label: 'Fee' },
	{ key: 'created', label: 'Created on' },
	{ key: 'available_on', label: 'Available on' },
];

export default () => {
	const [ response, setResponse ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/wc/v3/payments/transactions' } ).then( setResponse );
	}, [] );

	const rows = response && response.data.map( ( { type, status, description, amount, fee, created, available_on } ) => [
		{ value: type, display: type[ 0 ].toUpperCase() + type.slice( 1 ) },
		{ value: status, display: status[ 0 ].toUpperCase() + status.slice( 1 ) },
		{ value: description, display: description },
		{ value: amount / 100, display: formatCurrency( amount / 100 ) },
		{ value: fee / 100, display: formatCurrency( fee / 100 ) },
		{ value: created * 1000, display: dateI18n( 'Y-m-d H:i', moment( created * 1000 ) ) },
		{ value: available_on * 1000, display: dateI18n( 'Y-m-d H:i', moment( available_on * 1000 ) ) },
	] );

	return (
		<TableCard
			title="Transactions"
			isLoading={ ! response }
			rowsPerPage={ 10 }
			totalRows={ 10 }
			headers={ headers }
			rows={ rows || [] }
		/>
	);
};
