/** @format **/

/**
 * External dependencies
 */
import { withSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { CHARGES_STORE_NAME } from '../data';
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
	const { getCharge, isResolving } = select( CHARGES_STORE_NAME );
	const charge = getCharge( ownProps.query.id );
	const showPlaceholder = isResolving( 'getCharge', [ ownProps.query.id ] );

	return { charge, showPlaceholder };
} )( PaymentDetails );
