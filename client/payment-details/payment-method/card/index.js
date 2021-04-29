/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import PaymentDetailsPaymentMethodCheck from './check';
import PaymentDetailsPaymentMethodDetail from '../detail';

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {Object} charge The charge object.
 * @return {Object}       A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = ( charge ) => {
	const { billing_details: billingDetails, payment_method: id } = charge;

	const {
		last4,
		fingerprint,
		exp_month: month,
		exp_year: year,
		funding,
		network,
		country: countryCode,
		checks,
	} = charge.payment_method_details.card;

	const { name, email, formatted_address: formattedAddress } = billingDetails;
	const {
		cvc_check: cvcCheck,
		address_line1_check: line1Check,
		address_postal_code_check: postalCodeCheck,
	} = checks;

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
		network.charAt( 0 ).toUpperCase() + network.slice( 1 ), // Brand
		fundingTypes[ funding ]
	);

	// Use the full country name.
	const country = wcSettings.countries[ countryCode ];

	return {
		last4,
		fingerprint,
		date,
		cardType,
		id,
		name,
		email,
		country,
		cvcCheck,
		line1Check,
		postalCodeCheck,
		formattedAddress,
	};
};

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders = {
	last4: '0000',
	fingerprint: 'fingerprint placeholder',
	date: 'date placeholder',
	cardType: 'card type placeholder',
	id: 'id placeholder',
	name: 'name placeholder',
	email: 'email placeholder',
	formattedAddress: 'address placeholder',
	country: 'country placeholder',
	cvcCheck: null,
	line1Check: null,
	postalCodeCheck: null,
};

const CardDetails = ( { charge = {}, isLoading } ) => {
	const details =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const {
		last4,
		fingerprint,
		date,
		cardType,
		id,
		name,
		email,
		country,
		cvcCheck,
		line1Check,
		postalCodeCheck,
		formattedAddress,
	} = details;

	// Shorthand for more readable code.
	const Detail = PaymentDetailsPaymentMethodDetail;
	const Check = PaymentDetailsPaymentMethodCheck;

	return (
		<div className="payment-method-details">
			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'Number', 'woocommerce-payments' ) }
				>
					&bull;&bull;&bull;&bull;&nbsp;{ last4 }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Fingerprint', 'woocommerce-payments' ) }
				>
					{ fingerprint }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Expires', 'woocommerce-payments' ) }
				>
					{ date }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Type', 'woocommerce-payments' ) }
				>
					{ cardType }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'ID', 'woocommerce-payments' ) }
				>
					{ id }
				</Detail>
			</div>

			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'Owner', 'woocommerce-payments' ) }
				>
					{ name }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Owner email', 'woocommerce-payments' ) }
				>
					{ email }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Address', 'woocommerce-payments' ) }
				>
					<span
						dangerouslySetInnerHTML={ {
							__html: formattedAddress,
						} }
					/>
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Origin', 'woocommerce-payments' ) }
				>
					{ country }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'CVC check', 'woocommerce-payments' ) }
				>
					<Check checked={ cvcCheck } />
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Street check', 'woocommerce-payments' ) }
				>
					<Check checked={ line1Check } />
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Zip check', 'woocommerce-payments' ) }
				>
					<Check checked={ postalCodeCheck } />
				</Detail>
			</div>
		</div>
	);
};

export default CardDetails;
