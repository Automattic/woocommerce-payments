/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';

const PaymentDetailsPaymentMethodDetail = ( props ) => {
	const { label, children, isLoading } = props;

	return (
		<div className="payment-method-detail">
			<h4 className="payment-method-detail__label">
				<Loadable isLoading={ isLoading } display="block">
					{ label }
				</Loadable>
			</h4>
			<p className="payment-method-detail__value">
				<Loadable isLoading={ isLoading }>{ children }</Loadable>
			</p>
		</div>
	);
};

export default PaymentDetailsPaymentMethodDetail;
