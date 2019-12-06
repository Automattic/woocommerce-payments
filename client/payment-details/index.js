/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import withSelect from 'payments-api/with-select';
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPayment from './payment';
import PaymentDetailsPaymentMethod from './payment-method';
import PaymentDetailsSession from './session';

const PaymentDetails = ( props ) => {
	const { transaction } = props;
	return (
		<div>
			<PaymentDetailsSummary transaction={ transaction }></PaymentDetailsSummary>
			<PaymentDetailsTimeline transaction={ transaction }></PaymentDetailsTimeline>
			<PaymentDetailsPayment transaction={ transaction }></PaymentDetailsPayment>
			<PaymentDetailsPaymentMethod transaction={ transaction }></PaymentDetailsPaymentMethod>
			<PaymentDetailsSession transaction={ transaction }></PaymentDetailsSession>
		</div>
	);
};

export default withSelect( ( select, ownProps ) => {
	const { getCharge, isChargeWaitingForInitialLoad } = select( 'wc-payments-api' );
	const transaction = getCharge( ownProps.query.id );
	const showPlaceholder = isChargeWaitingForInitialLoad( ownProps.query.id );

	return { transaction, showPlaceholder };
} )( PaymentDetails );
