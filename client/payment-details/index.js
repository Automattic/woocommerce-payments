/** @format **/

/**
 * Internal dependencies
 */
import { useCharge } from '../data';
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPayment from './payment';
import PaymentDetailsPaymentMethod from './payment-method';
import PaymentDetailsSession from './session';
import Page from 'components/page';

const PaymentDetails = ( props ) => {
	const chargeId = props.query.id;
	const { charge, isLoading } = useCharge( chargeId );
	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			<PaymentDetailsSummary charge={ charge } isLoading={ isLoading } />
			<PaymentDetailsTimeline charge={ charge } />
			{ // Hidden for the beta.
				false && <PaymentDetailsPayment charge={ charge } /> }
			<PaymentDetailsPaymentMethod charge={ charge } isLoading={ isLoading } />
			{ // Hidden for the beta.
				false && <PaymentDetailsSession charge={ charge } /> }
		</Page>
	);
};

export default PaymentDetails;
