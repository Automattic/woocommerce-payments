/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Detail from '../detail';

/**
 * Extracts and formats payment method details from a charge.
 *
 * @param {Object} charge The charge object.
 * @return {Object}       A flat hash of all necessary values.
 */
const formatPaymentMethodDetails = ( charge ) => {
	const { billing_details: billingDetails, payment_method: id } = charge;

	const {
		payment_method_category: paymentMethodCategory,
		preferred_locale: preferredLocale,
	} = charge.payment_method_details.klarna;

	const paymentMethodCategoryTranslations = {
		pay_later: __( 'pay_later', 'woocommerce-payments' ),
		pay_now: __( 'pay_now', 'woocommerce-payments' ),
		pay_with_financing: __( 'pay_with_financing', 'woocommerce-payments' ),
		pay_in_installments: __(
			'pay_in_installments',
			'woocommerce-payments'
		),
	};

	const { name, email, formatted_address: formattedAddress } = billingDetails;

	return {
		id,
		name,
		email,
		formattedAddress,
		paymentMethodCategory:
			paymentMethodCategoryTranslations[ paymentMethodCategory ],
		preferredLocale,
	};
};

/**
 * Placeholders to display while loading.
 */
const paymentMethodPlaceholders = {
	id: 'id placeholder',
	name: 'name placeholder',
	email: 'email placeholder',
	formattedAddress: 'address placeholder',
	paymentMethodCategory: 'category placeholder',
	preferredLocale: 'locale placeholder',
};

const KlarnaDetails = ( { charge = {}, isLoading } ) => {
	const details = charge.payment_method_details
		? formatPaymentMethodDetails( charge )
		: paymentMethodPlaceholders;

	const {
		id,
		name,
		email,
		formattedAddress,
		paymentMethodCategory,
		preferredLocale,
	} = details;

	return (
		<div className="payment-method-details">
			<div className="payment-method-details__column">
				<Detail
					isLoading={ isLoading }
					label={ __( 'ID', 'woocommerce-payments' ) }
				>
					{ id }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Category', 'woocommerce-payments' ) }
				>
					{ paymentMethodCategory }
				</Detail>

				<Detail
					isLoading={ isLoading }
					label={ __( 'Preferred Locale', 'woocommerce-payments' ) }
				>
					{ preferredLocale }
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
			</div>
		</div>
	);
};

export default KlarnaDetails;
