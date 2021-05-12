/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import GiropayIcon from '../../gateway-icons/giropay';
import SepaIcon from '../../gateway-icons/sepa';
import SofortIcon from '../../gateway-icons/sofort';
import ApplePayIcon from '../../gateway-icons/apple-pay';
import GooglePayIcon from '../../gateway-icons/google-pay';

const paymentMethods = {
	giropay: {
		label: __( 'GiroPay', 'woocommerce-payments' ),
		Icon: GiropayIcon,
	},
	sepa: {
		label: __( 'Direct Debit Payments', 'woocommerce-payments' ),
		Icon: SepaIcon,
	},
	sofort: {
		label: __( 'Sofort', 'woocommerce-payments' ),
		Icon: SofortIcon,
	},
	'apple-pay': {
		label: __( 'Apple Pay', 'woocommerce-payments' ),
		Icon: ApplePayIcon,
	},
	'google-pay': {
		label: __( 'Google Pay', 'woocommerce-payments' ),
		Icon: GooglePayIcon,
	},
};

const PaymentMethodIcon = ( props ) => {
	const { name, showName } = props;

	const paymentMethod = paymentMethods[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	const { label, Icon } = paymentMethod;

	return (
		<span className="woocommerce-payments__payment-method-icon">
			<Icon />
			{ showName && (
				<span className="woocommerce-payments__payment-method-icon__label">
					{ label }
				</span>
			) }
		</span>
	);
};

export default PaymentMethodIcon;
