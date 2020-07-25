/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';
import PaymentDetailsPaymentMethodDetail from './detail';
import PaymentDetailsPaymentMethodCheck from './check';
import { formatPaymentMethodDetails } from './PaymentMethod.gen';

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

const PaymentDetailsPaymentMethod = ( { charge = {}, isLoading } ) => {
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
		<Card
			title={
				<Loadable
					isLoading={ isLoading }
					value={ __( 'Payment method', 'woocommerce-payments' ) }
				/>
			}
		>
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
		</Card>
	);
};

export default PaymentDetailsPaymentMethod;
