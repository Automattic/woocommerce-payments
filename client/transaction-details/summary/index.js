/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { formatCurrency } from '@woocommerce/currency';

/**
 * Internal dependencies.
 */
import PaymentStatusChip from '../../components/payment-status-chip';
import './style.scss';

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card>
			<div className="transaction-summary">
				<div className="transaction-summary__section">
					<h1 className="transaction-summary__amount">
						{ formatCurrency( ( transaction.amount || 0 ) / 100 ) }
						<span className="transaction-summary__amount-currency">{ ( transaction.currency || 'cur' ) }</span>
						<PaymentStatusChip transaction={ transaction } />
					</h1>
					<div className="transaction-summary__breakdown">
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
				</div>
			</div>
		</Card>
	);
};

export default TransactionSummaryDetails;
