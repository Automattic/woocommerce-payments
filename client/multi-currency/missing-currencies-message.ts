/**
 * External dependencies
 */
import { sprintf, _n } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatListOfItems } from 'wcpay/utils/format-list-of-items';

export const getMissingCurrenciesTooltipMessage = (
	paymentMethodLabel: string,
	missingCurrencies: string[]
) =>
	sprintf(
		_n(
			/* translators: %1: name of payment method being setup %2: name of missing currency (or currencies) that will be added */
			'%1$s requires the %2$s currency. In order to enable the payment method, you must add this currency to your store.',
			'%1$s requires the %2$s currencies. In order to enable the payment method, you must add these currencies to your store.',
			missingCurrencies.length,
			'woocommerce-payments'
		),
		paymentMethodLabel,
		formatListOfItems( missingCurrencies )
	);
