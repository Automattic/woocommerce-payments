/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Card } from '@woocommerce/components';
import { formatCurrency } from '@woocommerce/currency';
import { get } from 'lodash';

/**
 * Internal dependencies.
 */
import {
	isTransactionRefunded,
	isTransactionPartiallyRefunded,
	isTransactionFullyRefunded,
} from '../../utils/transaction';
import PaymentStatusChip from '../../components/payment-status-chip';
import './style.scss';

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card>
			<div className="transaction-summary">
				<div className="transaction-summary__section">
					<p className="transaction-summary__amount">
						{ formatCurrency( ( transaction.amount || 0 ) / 100 ) }
						<span className="transaction-summary__amount-currency">{ ( transaction.currency || 'cur' ) }</span>
						<PaymentStatusChip transaction={ transaction } />
					</p>
					<div className="transaction-summary__breakdown">
						{ isTransactionRefunded( transaction )
							? <p>
								{ `${ __( 'Refunded', 'woocommerce-payments' ) }: ` }
								{ formatCurrency( ( -get( transaction, 'source.amount_refunded' ) || 0 ) / 100 ) }
							</p>
							: '' }
						<p>
							{ `${ __( 'Fee', 'woocommerce-payments' ) }: ` }
							{ formatCurrency( ( -transaction.fee || 0 ) / 100 ) }
						</p>
						<p>
							{ `${ __( 'Net', 'woocommerce-payments' ) }: ` }
							{ formatCurrency( ( transaction.net || 0 ) / 100 ) }
						</p>
					</div>
				</div>
				<div className="transaction-summary__section">
					{ /* TODO: implement control buttons depending on the transaction status */ }
					{ /* E.g. if transaction is under dispute display Accept Dispute and Respond to Dispute buttons */ }
					<div className="transaction-summary__actions">
						<Button className="transaction-summary__actions-item"
							isDefault
							isLarge
							disabled={ ! get( transaction, 'order.url' ) ||	isTransactionFullyRefunded( transaction ) }
							href={ `${ get( transaction, 'order.url' ) }#woocommerce-order-items` }>
							{ ( isTransactionPartiallyRefunded( transaction ) )
								? __( 'Refund more', 'woocommerce-payments' )
								: __( 'Refund', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</div>
			<hr className="full-width" />
		</Card>
	);
};

export default TransactionSummaryDetails;
