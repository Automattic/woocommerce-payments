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
	{ key: 'created', label: 'Date / Time', required: true, isLeftAligned: true, defaultSort: true, defaultOrder: 'desc' },
	{ key: 'type', label: 'Type', required: true },
	{ key: 'source', label: 'Source' },
	// { key: 'order', label: 'Order #', required: true },
	{ key: 'customer', label: 'Customer' },
	{ key: 'email', label: 'Email', hiddenByDefault: true },
	{ key: 'country', label: 'Country', hiddenByDefault: true },
	{ key: 'amount', label: 'Amount', isNumeric: true },
	{ key: 'fee', label: 'Fees', isNumeric: true },
	{ key: 'net', label: 'Net', isNumeric: true, required: true },
	// TODO { key: 'deposit', label: 'Deposit', required: true },
	{ key: 'risk_level', label: 'Risk Level', hiddenByDefault: true },
];

export default () => {
	const [ transactions, setTransactions ] = useState( [] );
	const [ loading, setLoading ] = useState( false );

	useEffect( () => {
		setLoading( true );
		apiFetch( { path: '/wc/v3/payments/transactions' } ).then( ( response ) => {
			const { data } = response;
			if ( data ) {
				setTransactions( data );
			} else {
				console.error( response );
			}

			setLoading( false );
		} );
	}, [] );

	const rows = transactions.map( ( txn ) => {
		const charge = txn.source.object === 'charge' ? txn.source : null;

		const data = {
			created: { value: txn.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( txn.created * 1000 ) ) },
			type: { value: txn.type, display: capitalize( txn.type ) },
			source: charge && { value: charge.payment_method_details.card.brand, display: <code>{ charge.payment_method_details.card.brand }</code> },
			// TODO order: {},
			customer: charge && { value: charge.billing_details.name, display: charge.billing_details.name },
			email: charge && { value: charge.billing_details.email, display: charge.billing_details.email },
			country: charge && { value: charge.billing_details.address.country, display: charge.billing_details.address.country },
			amount: { value: txn.amount / 100, display: formatCurrency( txn.amount / 100 ) },
			fee: { value: txn.fee / 100, display: formatCurrency( txn.fee / 100 ) },
			net: { value: ( txn.amount - txn.fee ) / 100, display: formatCurrency( ( txn.amount - txn.fee ) / 100 ) },
			// TODO deposit: { value: available_on * 1000, display: dateI18n( 'Y-m-d H:i', moment( available_on * 1000 ) ) },
			risk_level: charge && { value: charge.outcome.risk_level, display: capitalize( charge.outcome.risk_level ) },
		};

		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	return (
		<TableCard
			title="Transactions"
			isLoading={ loading }
			rowsPerPage={ 10 }
			totalRows={ 10 }
			headers={ headers }
			rows={ rows }
		/>
	);
};
