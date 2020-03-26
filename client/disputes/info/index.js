/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import OrderLink from 'components/order-link';
import { getDetailsURL } from 'components/details-link';
import { reasons } from '../strings';
import { formatStringValue } from '../../util';
import './style.scss';
import Loadable from 'components/loadable';

const currency = new Currency();

const fields = [
	{ key: 'created', label: __( 'Dispute date', 'woocommerce-payments' ) },
	{ key: 'amount', label: __( 'Disputed amount', 'woocommerce-payments' ) },
	{ key: 'dueBy', label: __( 'Respond by', 'woocommerce-payments' ) },
	{ key: 'reason', label: __( 'Reason', 'woocommerce-payments' ) },
	{ key: 'order', label: __( 'Order', 'woocommerce-payments' ) },
	{ key: 'customer', label: __( 'Customer', 'woocommerce-payments' ) },
	{ key: 'transactionId', label: __( 'Transaction ID', 'woocommerce-payments' ) },
];

const composeTransactionIdLink = dispute => {
	const chargeId = typeof dispute.charge === 'object' ? dispute.charge.id : dispute.charge;
	return <Link href={ getDetailsURL( chargeId, 'transactions' ) } >{ chargeId }</Link>;
};

const composeDisputeReason = dispute => {
	const reasonMapping = reasons[ dispute.reason ];
	return reasonMapping ? reasonMapping.display : formatStringValue( dispute.reason );
};

const Info = ( { dispute, isLoading } ) => {
	const data = isLoading ? {
				created: 'Created placeholder',
				amount: 'Amount placeholder',
				dueBy: 'Due by placeholder',
				reason: 'Reason placeholder',
				order: 'Order link placeholder',
				customer: 'Customer placeholder',
				transactionId: 'Transaction placeholder',
		} : {
				created: dateI18n( 'M j, Y', moment( dispute.created * 1000 ) ),
				amount: `${ currency.formatCurrency( dispute.amount / 100 ) } ${ currency.code }`,
				dueBy: dateI18n( 'M j, Y - g:iA', moment( dispute.evidence_details.due_by * 1000 ) ),
				reason: composeDisputeReason( dispute ),
				order: dispute.order ? ( <OrderLink order={ dispute.order } /> ) : null,
				customer: typeof dispute.charge === 'object' ? dispute.charge.billing_details.name : null,
				transactionId: composeTransactionIdLink( dispute ),
		};

	return (
		<div className="wcpay-dispute-info">
			{ fields.map( ( { key, label } ) => {
				if ( null == data[ key ] ) {
					return null;
				}
				return (
					<div key={ key } className="wcpay-dispute-info-item">
						<Loadable isLoading={ isLoading }>
							<span className="wcpay-dispute-info-key">{`${ label }: `}</span>
							<span className="wcpay-dispute-info-value">
								{data[ key ]}
							</span>
						</Loadable>
					</div>
				);
			} ) }
		</div>
	);
};

export default Info;
