/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import CreditCardIcon from '../../gateway-icons/credit-card';
import GiropayIcon from '../../gateway-icons/giropay';
import SepaIcon from '../../gateway-icons/sepa';
import SofortIcon from '../../gateway-icons/sofort';

const paymentMethods = {
	card: {
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		Icon: CreditCardIcon,
	},
	giropay: {
		label: __( 'GiroPay', 'woocommerce-payments' ),
		Icon: GiropayIcon,
	},
	sepa_debit: {
		label: __( 'Direct Debit Payments', 'woocommerce-payments' ),
		Icon: SepaIcon,
	},
	sofort: {
		label: __( 'Sofort', 'woocommerce-payments' ),
		Icon: SofortIcon,
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
