/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import GiroPayLogo from './giropay.svg';
import SepaLogo from './sepa.svg';
import SofortLogo from './sofort.svg';

const paymentMethods = {
	giropay: {
		label: __( 'GiroPay', 'woocommerce-payments' ),
		logo: GiroPayLogo,
	},
	sepa: {
		label: __( 'Direct Debit Payments', 'woocommerce-payments' ),
		logo: SepaLogo,
	},
	sofort: {
		label: __( 'Sofort', 'woocommerce-payments' ),
		logo: SofortLogo,
	},
};

const PaymentMethodIcon = ( props ) => {
	const { name, showName } = props;

	const paymentMethod = paymentMethods[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	const { label, logo } = paymentMethod;

	return (
		<span className="woocommerce-payments__payment-method-icon">
			<img alt={ label } src={ logo } />
			{ showName && (
				<span className="woocommerce-payments__payment-method-icon__label">
					{ label }
				</span>
			) }
		</span>
	);
};

export default PaymentMethodIcon;
