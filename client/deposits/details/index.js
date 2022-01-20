/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { Card } from '@wordpress/components';
import {
	SummaryListPlaceholder,
	SummaryList,
	OrderStatus,
} from '@woocommerce/components';
import classNames from 'classnames';

/**
 * Internal dependencies.
 */
import { useDeposit } from 'wcpay/data';
import { displayStatus } from '../strings';
import TransactionsList from 'transactions/list';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import './style.scss';

const Status = ( { status } ) => (
	// Re-purpose order status indicator for deposit status.
	<OrderStatus order={ { status } } orderStatusMap={ displayStatus } />
);

// Custom SummaryNumber with custom value className reusing @woocommerce/components styles.
const SummaryItem = ( { label, value, valueClass, detail } ) => (
	<li className="woocommerce-summary__item-container">
		<div className="woocommerce-summary__item">
			<div className="woocommerce-summary__item-label">{ label }</div>
			<div className="woocommerce-summary__item-data">
				<div
					className={ classNames(
						'woocommerce-summary__item-value',
						valueClass
					) }
				>
					{ value }
				</div>
			</div>
			{ detail && (
				<div className="wcpay-summary__item-detail">{ detail }</div>
			) }
		</div>
	</li>
);

export const DepositOverview = ( { depositId } ) => {
	const { deposit = {}, isLoading } = useDeposit( depositId );

	const depositDateLabel = deposit.automatic
		? __( 'Deposit date', 'woocommerce-payments' )
		: __( 'Instant deposit date', 'woocommerce-payments' );

	const depositDateItem = (
		<SummaryItem
			key="depositDate"
			label={
				`${ depositDateLabel }: ` +
				dateI18n(
					'M j, Y',
					moment.utc( deposit.date ).toISOString(),
					true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
				)
			}
			value={ <Status status={ deposit.status } /> }
			detail={ deposit.bankAccount }
		/>
	);

	if ( isLoading ) return <SummaryListPlaceholder numberOfItems={ 2 } />;
	return (
		<div className="wcpay-deposit-overview">
			{ deposit.automatic ? (
				<Card className="wcpay-deposit-automatic">
					<ul>
						{ depositDateItem }
						<li className="wcpay-deposit-amount">
							{ formatExplicitCurrency(
								deposit.amount,
								deposit.currency
							) }
						</li>
					</ul>
				</Card>
			) : (
				<SummaryList
					label={ __( 'Deposit overview', 'woocommerce-payments' ) }
				>
					{ () => [
						depositDateItem,
						<SummaryItem
							key="depositAmount"
							label={ __(
								'Deposit amount',
								'woocommerce-payments'
							) }
							value={ formatExplicitCurrency(
								deposit.amount + deposit.fee,
								deposit.currency
							) }
						/>,
						<SummaryItem
							key="depositFees"
							label={ sprintf(
								/* translators: %s - amount representing the fee percentage */
								__( '%s service fee', 'woocommerce-payments' ),
								`${ deposit.fee_percentage }%`
							) }
							value={ formatCurrency(
								deposit.fee,
								deposit.currency
							) }
							valueClass={
								0 < deposit.fee && 'wcpay-deposit-fee'
							}
						/>,
						<SummaryItem
							key="netDepositAmount"
							label={ __(
								'Net deposit amount',
								'woocommerce-payments'
							) }
							value={ formatExplicitCurrency(
								deposit.amount,
								deposit.currency
							) }
							valueClass="wcpay-deposit-net"
						/>,
					] }
				</SummaryList>
			) }
		</div>
	);
};

export const DepositDetails = ( { query: { id: depositId } } ) => (
	<Page>
		<TestModeNotice topic={ topics.depositDetails } />
		<ErrorBoundary>
			<DepositOverview depositId={ depositId } />
		</ErrorBoundary>
		<ErrorBoundary>
			<TransactionsList depositId={ depositId } />
		</ErrorBoundary>
	</Page>
);

export default DepositDetails;
