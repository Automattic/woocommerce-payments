/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { Card, OrderStatus } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import { useDeposit } from 'data';
import { displayStatus } from '../strings';
import TransactionsList from 'transactions';
import './style.scss';

const currency = new Currency();

const Status = ( { status } ) => (
	// Re-purpose order status indicator for deposit status.
	<OrderStatus order={ { status } } orderStatusMap={ displayStatus } />
);

export const DepositOverview = ( { depositId } ) => {
	const { deposit, isLoading } = useDeposit( depositId );

	if ( isLoading ) {
		return <p>Loading…</p>;
	}
	if ( ! deposit ) {
		return null;
	}

	return (
		<Card className="wcpay-deposit-overview">
			<div className="wcpay-deposit-detail">
				<div className="wcpay-deposit-date">
					{ `${ __( 'Deposit date', 'woocommerce-payments' ) }: ` }
					{ dateI18n( 'M j, Y', moment.utc( deposit.date ).local() ) }
				</div>
				<div className="wcpay-deposit-status">
					<Status status={ deposit.status } />
				</div>
				<div className="wcpay-deposit-bank-account">
					{ deposit.bankAccount }
				</div>
			</div>

			<div className="wcpay-deposit-hero">
				<div className="wcpay-deposit-amount">
					{ currency.formatCurrency( deposit.amount / 100 ) }
				</div>
			</div>
		</Card>
	);
};

export const DepositDetails = ( { query: { id: depositId } } ) => (
	<>
		<DepositOverview depositId={ depositId } />
		<TransactionsList depositId={ depositId } />
	</>
);

export default DepositDetails;
