/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';
import PaymentDetailsPaymentMethodDetail from './detail';
import PaymentDetailsPaymentMethodCheck from './check';

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

const PaymentDetailsPaymentMethod = ( { charge = {}, isLoading } ) => {
	const details = ( charge && charge.payment_method_details )
		? formatPaymentMethodDetails( charge )
		: paymentMethodPlaceholders;

	const {
		last4, fingerprint, date, cardType, id, name, email, country,
		cvcCheck, line1Check, postalCodeCheck, formattedAddress,
	} = details;

	// Shorthand for more readable code.
	const Detail = PaymentDetailsPaymentMethodDetail;
	const Check = PaymentDetailsPaymentMethodCheck;

	return (
		<Card title={ __( 'Payment method', 'woocommerce-payments' ) }>
			<div className="payment-method-details">
				<div className="payment-method-details__column">
					<Detail label={ __( 'Number', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="**** 0000">
							&bull;&bull;&bull;&bull; { last4 }
						</Loadable>
					</Detail>

					<Detail label={ __( 'Fingerprint', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Fingerprint placeholder" value={ fingerprint } />
					</Detail>

					<Detail label={ __( 'Expires', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Date placeholder" value={ date } />
					</Detail>

					<Detail label={ __( 'Type', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Card type placeholder" value={ cardType } />
					</Detail>

					<Detail label={ __( 'ID', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Payment ID placeholder" value={ id } />
					</Detail>
				</div>

				<div className="payment-method-details__column">
					<Detail label={ __( 'Owner', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Owner placeholder" value={ name } />
					</Detail>

					<Detail label={ __( 'Owner Email', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Email placeholder" value={ email } />
					</Detail>

					<Detail label={ __( 'Address', 'woocommerce-payments' ) }>
						<Loadable
							isLoading={ isLoading }
							display="inline"
							placeholder={ <span>Address placeholder line 1 <br /> placholder line 2 </span> }
						>
							<span dangerouslySetInnerHTML={ { __html: formattedAddress } } />
						</Loadable>
					</Detail>

					<Detail label={ __( 'Origin', 'woocommerce-payments' ) }>
						<Loadable isLoading={ isLoading } placeholder="Origin placeholder" value={ country } />
					</Detail>

					<Detail label={ __( 'CVC check', 'woocommerce-payments' ) }>
						<Loadable
							isLoading={ isLoading }
							placeholder="Check"
							value={ <Check checked={ cvcCheck } /> }
						/>
					</Detail>

					<Detail label={ __( 'Street check', 'woocommerce-payments' ) }>
						<Loadable
							isLoading={ isLoading }
							placeholder="Check"
							value={ <Check checked={ line1Check } /> }
						/>
					</Detail>

					<Detail label={ __( 'Zip check', 'woocommerce-payments' ) }>
						<Loadable
							isLoading={ isLoading }
							placeholder="Check"
							value={ <Check checked={ postalCodeCheck } /> }
						/>
					</Detail>
				</div>
			</div>
		</Card>
	);
};

export default PaymentDetailsPaymentMethod;
