/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useDeposit } from 'data';
import TransactionsList from 'transactions';

export const DepositOverview = ( { depositId } ) => {
	const { deposit, isLoading } = useDeposit( depositId );

	if ( isLoading ) {
		return <p>Loading…</p>;
	}
	if ( ! deposit ) {
		return null;
	}

	return (
		<div className="wcpay-deposit-overview">
			<pre>{ JSON.stringify( deposit, null, 2 ) }</pre>
		</div>
	);
};

export const DepositDetails = ( { query: { id: depositId } } ) => (
	<>
		<DepositOverview depositId={ depositId } />
		<TransactionsList depositId={ depositId } />
	</>
);

export default DepositDetails;
