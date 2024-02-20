/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import PaymentDetailsPaymentMethodDetail from '../detail';

/**
 * Extracts and formats payment method details from a card-present charge.
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
	} = charge.payment_method_details.card_present;

	const { name, email, formatted_address: formattedAddress } = billingDetails;

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
		formattedAddress,
	};
};

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders = {
	last4: '0000',
	fingerprint: __( 'fingerprint placeholder', 'woocommerce-payments' ),
	date: __( 'date placeholder', 'woocommerce-payments' ),
	cardType: __( 'card type placeholder', 'woocommerce-payments' ),
	id: __( 'id placeholder', 'woocommerce-payments' ),
	name: __( 'name placeholder', 'woocommerce-payments' ),
	email: __( 'email placeholder', 'woocommerce-payments' ),
	formattedAddress: __( 'address placeholder', 'woocommerce-payments' ),
	country: __( 'country placeholder', 'woocommerce-payments' ),
};

const CardPresentDetails = ( { charge = {}, isLoading } ) => {
	const details =
		charge && charge.payment_method_details
			? formatPaymentMethodDetails( charge )
			: paymentMethodPlaceholders;

	const {
		last4,
		date,
		cardType,
		id,
		name,
		email,
		country,
		formattedAddress,
	} = details;

	// Shorthand for more readable code.
	const Detail = PaymentDetailsPaymentMethodDetail;

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
			</div>
		</div>
	);
};

export default CardPresentDetails;
