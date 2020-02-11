/** @format **/
/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import { useCharge } from '../data';
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPayment from './payment';
import PaymentDetailsPaymentMethod from './payment-method';
import PaymentDetailsSession from './session';

const PaymentDetails = ( props ) => {
	const chargeId = props.query.id;
	const { charge } = useCharge( chargeId );
	return (
		<div>
			<PaymentDetailsSummary charge={ charge }></PaymentDetailsSummary>
			<PaymentDetailsTimeline charge={ charge }></PaymentDetailsTimeline>
			{ // Hidden for the private beta.
				false && <PaymentDetailsPayment charge={ charge }></PaymentDetailsPayment> }
			<PaymentDetailsPaymentMethod charge={ charge }></PaymentDetailsPaymentMethod>
			{ // Hidden for the private beta.
				false && <PaymentDetailsSession charge={ charge }></PaymentDetailsSession> }
		</div>
	);
};

export default PaymentDetails;
