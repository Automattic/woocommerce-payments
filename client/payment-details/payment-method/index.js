/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import PaymentDetailsPaymentMethodDetail from './detail';

const PaymentDetailsPaymentMethod = ( props ) => {
	if ( ! props.charge.payment_method_details ) {
		return <Card title={ __( 'Payment method', 'woocommerce-payments' ) }>Loading...</Card>;
	}

	const {
		charge: {
			billing_details: billingDetails,
			payment_method: id,
			payment_method_details: { card },
		},
	} = props;
	const {
		last4,
		fingerprint,
		exp_month: month,
		exp_year: year,
		funding,
		network,
		checks: {
			cvc_check: cvcCheck,
			address_line1_check: line1Check,
			address_postal_code_check: postalCodeCheck,
		},
	} = card;
	const {
		name,
		email,
		address: {
			city,
			country,
			line1,
			line2,
			postal_code: postalCode,
			state,
		},
	} = billingDetails;

	const fullAddress = line1 + '\n' + line2 + '\n' + city + ' ' + state + ', ' + postalCode + ', ' + country;

	return (
		<Card title={ __( 'Payment method', 'woocommerce-payments' ) }>
			<div style={ { float: 'left', width: '50%' } }>
				<PaymentDetailsPaymentMethodDetail label={ __( 'Number', 'woocommerce-payments' ) }>
					&bull;&bull;&bull;&bull; { last4 }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Fingerprint', 'woocommerce-payments' ) }>
					{ fingerprint }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Expires', 'woocommerce-payments' ) }>
					{ month + ' / ' + year }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Type', 'woocommerce-payments' ) }>
					{ network + ' ' + ( ( 'credit' === funding ) ? 'credit' : 'prepaid' ) + ' card' }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'ID', 'woocommerce-payments' ) }>
					{ id }
				</PaymentDetailsPaymentMethodDetail>
			</div>

			<div style={ { float: 'right', width: '50%' } }>
				<PaymentDetailsPaymentMethodDetail label={ __( 'Owner', 'woocommerce-payments' ) }>
					{ name }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Owner Email', 'woocommerce-payments' ) }>
					{ email }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Address', 'woocommerce-payments' ) }>
					{ fullAddress }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Origin', 'woocommerce-payments' ) }>
					{ country }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'CVC check', 'woocommerce-payments' ) }>
					{ ( 'pass' === cvcCheck )
						? __( 'Passed', 'woocommerce-payments' )
						: __( 'Not ', 'woocommerce-payments' ) }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Street check', 'woocommerce-payments' ) }>
					{ ( 'pass' === line1Check )
						? __( 'Passed', 'woocommerce-payments' )
						: __( 'Not ', 'woocommerce-payments' ) }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Zip check', 'woocommerce-payments' ) }>
					{ ( 'pass' === postalCodeCheck )
						? __( 'Passed', 'woocommerce-payments' )
						: __( 'Not ', 'woocommerce-payments' ) }
				</PaymentDetailsPaymentMethodDetail>
			</div>

			<div style={ { clear: 'both', height: '2px' } }></div>
		</Card>
	);
};

export default PaymentDetailsPaymentMethod;
