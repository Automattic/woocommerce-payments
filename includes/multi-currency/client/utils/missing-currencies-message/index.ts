/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Joins a list of items with "or" as the conjunction.
 * Example: [a, b] => "a or b", [a, b, c] => "a, b, or c".
 *
 * @param {string[]} items Items to join.
 * @return {string} Joined list.
 */
const joinWithOr = ( items: string[] ): string => {
	if ( items.length <= 1 ) {
		return items[ 0 ] ?? '';
	}

	if ( items.length === 2 ) {
		return sprintf(
			/* translators: %1$s: first item, %2$s: second item in a list of two alternatives */
			__( '%1$s or %2$s', 'woocommerce-payments' ),
			items[ 0 ],
			items[ 1 ]
		);
	}

	return sprintf(
		/* translators: %1$s: comma-separated list of items, %2$s: last item in a list of alternatives */
		__( '%1$s, or %2$s', 'woocommerce-payments' ),
		items.slice( 0, -1 ).join( ', ' ),
		items[ items.length - 1 ]
	);
};

export const getMissingCurrenciesTooltipMessage = (
	paymentMethodLabel: string,
	requiredCurrencies: string[]
) => {
	if ( requiredCurrencies.length === 1 ) {
		return sprintf(
			/* translators: %1$s: name of payment method, %2$s: name of the required currency */
			__(
				'%1$s requires the %2$s currency. Add %2$s to your store to offer this payment method.',
				'woocommerce-payments'
			),
			paymentMethodLabel,
			requiredCurrencies[ 0 ]
		);
	}

	return sprintf(
		/* translators: %1$s: name of payment method, %2$s: list of supported currencies joined by "or" (e.g. "EUR or PLN") */
		__(
			'%1$s requires at least one of the following currencies: %2$s. Add at least one of these currencies to your store to offer this payment method.',
			'woocommerce-payments'
		),
		paymentMethodLabel,
		joinWithOr( requiredCurrencies )
	);
};
