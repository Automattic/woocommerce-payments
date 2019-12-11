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
	const { charge } = props;
	return (
		<div>
			<PaymentDetailsSummary charge={ charge }></PaymentDetailsSummary>
			<PaymentDetailsTimeline charge={ charge }></PaymentDetailsTimeline>
			<PaymentDetailsPayment charge={ charge }></PaymentDetailsPayment>
			<PaymentDetailsPaymentMethod charge={ charge }></PaymentDetailsPaymentMethod>
			<PaymentDetailsSession charge={ charge }></PaymentDetailsSession>
		</div>
	);
};

export default withSelect( ( select, ownProps ) => {
	const { getCharge, isChargeWaitingForInitialLoad } = select( 'wc-payments-api' );
	const charge = getCharge( ownProps.query.id );
	const showPlaceholder = isChargeWaitingForInitialLoad( ownProps.query.id );

	return { charge, showPlaceholder };
} )( PaymentDetails );
