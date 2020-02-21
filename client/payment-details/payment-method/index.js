/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import PaymentDetailsPaymentMethodDetail from './detail';

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {object} charge The charge object.
 * @return {object}       A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = ( charge ) => {
	const {
		billing_details: billingDetails,
		payment_method: id,
		payment_method_details: { card },
	} = charge;

	const { last4, fingerprint, exp_month: month, exp_year: year, funding, network, country: countryCode, checks } = card;
	const { name, email, formatted_address: formattedAddress } = billingDetails;
	const { cvc_check: cvcCheck, address_line1_check: line1Check, address_postal_code_check: postalCodeCheck } = checks;

	// Format the date, MM/YYYY. No translations needed.
	const date = month + ' / ' + year;

	// Generate the full funding type.
	const fundingTypes = {
		credit: __( 'credit', 'woocommerce-payments' ),
		debit: __( 'debit', 'woocommerce-payments' ),
		prepaid: __( 'prepaid', 'woocommerce-payments' ),
		unknown: __( 'unknown', 'woocommerce-payments' ),
	};
	const cardType = sprintf(
		// Translators: %1$s card brand, %2$s card funding (prepaid, credit, etc.).
		__( '%1$s %2$s card', 'woocommerce-payments' ),
		( network.charAt( 0 ).toUpperCase() + network.slice( 1 ) ), // Brand
		fundingTypes[ funding ]
	);

	// Use the full country name.
	const country = wcSettings.countries[ countryCode ];

	return {
		last4, fingerprint, date, cardType, id, name, email, country, cvcCheck, line1Check, postalCodeCheck, formattedAddress,
	};
};

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders = {
	last4: '0000',
	fingerprint: '-',
	date: '-',
	cardType: '-',
	id: '-',
	name: '-',
	email: '-',
	formattedAddress: '-',
	country: '-',
	cvcCheck: null,
	line1Check: null,
	postalCodeCheck: null,
};

const PaymentDetailsPaymentMethod = ( props ) => {
	const details = ( props.charge && props.charge.payment_method_details )
		? formatPaymentMethodDetails( props.charge )
		: paymentMethodPlaceholders;

	const {
		last4, fingerprint, date, cardType, id, name, email, country,
		cvcCheck, line1Check, postalCodeCheck, formattedAddress,
	} = details;

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
					{ date }
				</PaymentDetailsPaymentMethodDetail>

				<PaymentDetailsPaymentMethodDetail label={ __( 'Type', 'woocommerce-payments' ) }>
					{ cardType }
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
					<span dangerouslySetInnerHTML={ { __html: formattedAddress } } />
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
