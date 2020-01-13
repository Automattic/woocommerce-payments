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
	// TODO: add placeholder view
	// eslint-disable-next-line no-unused-vars
	const { charge, loading: showPlaceholder } = useCharge( chargeId );
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

export default PaymentDetails;
