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
import TransactionsList from 'transactions/list';
import Page from 'components/page';
import Loadable from 'components/loadable';
import { TestModeNotice, topics } from 'components/test-mode-notice';
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
					<Loadable
						isLoading={ isLoading }
						placeholder="Date placeholder"
					>
						{ `${ __(
							'Deposit date',
							'woocommerce-payments'
						) }: ` }
						{ dateI18n(
							'M j, Y',
							moment.utc( deposit.date ).toISOString(),
							true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
						) }
					</Loadable>
				</div>
				<div className="wcpay-deposit-status">
					<Loadable isLoading={ isLoading } placeholder="Status">
						<Status status={ deposit.status } />
					</Loadable>
				</div>
				<div className="wcpay-deposit-bank-account">
					<Loadable
						isLoading={ isLoading }
						placeholder="Bank account placeholder"
					>
						{ deposit.bankAccount }
					</Loadable>
				</div>
			</div>

			<div className="wcpay-deposit-hero">
				<div className="wcpay-deposit-amount">
					<Loadable
						isLoading={ isLoading }
						placeholder="Amount"
						display="inline"
					>
						{ currency.formatCurrency( deposit.amount / 100 ) }
					</Loadable>
				</div>
			</div>
		</Card>
	);
};

export const DepositDetails = ( { query: { id: depositId } } ) => (
	<Page>
		<TestModeNotice topic={ topics.depositDetails } />
		<DepositOverview depositId={ depositId } />
		<TransactionsList depositId={ depositId } />
	</Page>
);

export default DepositDetails;
