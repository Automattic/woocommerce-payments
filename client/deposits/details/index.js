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
import Placeholder from 'components/placeholder';
import './style.scss';

const currency = new Currency();

const Status = ( { status } ) => (
	// Re-purpose order status indicator for deposit status.
	<OrderStatus order={ { status } } orderStatusMap={ displayStatus } />
);

export const DepositOverview = ( { depositId } ) => {
	const { deposit = {}, isLoading } = useDeposit( depositId );

	return (
		<Card className="wcpay-deposit-overview">
			<div className="wcpay-deposit-detail">
				<div className="wcpay-deposit-date">
					<Placeholder isActive={ isLoading } content="Date placeholder">
						{`${ __( 'Deposit date', 'woocommerce-payments' ) }: `}
						{dateI18n( 'M j, Y', moment.utc( deposit.date ).local() )}
					</Placeholder>
				</div>
				<div className="wcpay-deposit-status">
					<Placeholder
						isActive={ isLoading }
						content="Status placeholder"
					>
						<Status status={ deposit.status } />
					</Placeholder>
				</div>
				<div className="wcpay-deposit-bank-account">
					<Placeholder isActive={ isLoading } content="Bank account placeholder">
						{deposit.bankAccount}
					</Placeholder>
				</div>
			</div>

			<div className="wcpay-deposit-hero">
				<div className="wcpay-deposit-amount">
					<Placeholder
						display="inline"
						isActive={ isLoading }
						content="Amount"
					>
						{currency.formatCurrency( deposit.amount / 100 )}
					</Placeholder>
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
