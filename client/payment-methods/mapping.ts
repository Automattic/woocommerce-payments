/**
 * External dependencies
 */
import type { ImgHTMLAttributes, FunctionComponent } from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodDefinition } from './types';
import { PaymentMethodMapEntry } from 'wcpay/types/payment-methods';
import { createPaymentMethodIconComponent } from './icons';

type ReactImgFuncComponent = FunctionComponent<
	ImgHTMLAttributes< HTMLImageElement >
>;

/**
 * Maps payment method IDs to their corresponding icon components
 */
function getIconComponent(
	def: PaymentMethodDefinition
): ReactImgFuncComponent {
	return createPaymentMethodIconComponent( def );
}

/**
 * Maps a PaymentMethodDefinition to a PaymentMethodMapEntry
 * Note: Titles and descriptions are already translated in PHP payment method definition files.
 */
export function mapDefinitionToEntry(
	def: PaymentMethodDefinition
): PaymentMethodMapEntry {
	return {
		id: def.id,
		label: def.title,
		description: def.description,
		icon: getIconComponent( def ),
		currencies: def.currencies,
		stripe_key: def.stripeId,
		allows_manual_capture: def.allowsManualCapture,
		allows_pay_later: def.allowsPayLater,
		accepts_only_domestic_payment: def.acceptsOnlyDomesticPayment,
	};
}
