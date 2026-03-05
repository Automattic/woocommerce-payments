/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';

export const getMissingCurrenciesTooltipMessage = (
	paymentMethodLabel: string,
	missingCurrencies: string[]
) => {
	if ( missingCurrencies.length === 1 ) {
		return sprintf(
			/* translators: %1$s: name of payment method, %2$s: name of the required currency */
			__(
				'%1$s requires the %2$s currency. In order to enable the payment method, you must add this currency to your store.',
				'woocommerce-payments'
			),
			paymentMethodLabel,
			missingCurrencies[ 0 ]
		);
	}

	return sprintf(
		/* translators: %1$s: name of payment method, %2$s: list of supported currencies joined by " or " (e.g. "EUR or PLN") */
		__(
			'%1$s requires at least one of the following currencies: %2$s. You must add at least one of these currencies to your store.',
			'woocommerce-payments'
		),
		paymentMethodLabel,
		missingCurrencies.join(
			// translators: separator between currency codes, e.g. "EUR or PLN"
			__( ' or ', 'woocommerce-payments' )
		)
	);
};
