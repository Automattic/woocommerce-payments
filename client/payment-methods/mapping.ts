/**
 * External dependencies
 */
import type { ImgHTMLAttributes, FunctionComponent } from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodDefinition } from './types';
import type { PaymentMethodMapEntry } from '../payment-methods-map';
import {
	AffirmIcon,
	AfterpayIcon,
	ClearpayIcon,
} from '../payment-methods-icons';

type ReactImgFuncComponent = FunctionComponent<
	ImgHTMLAttributes< HTMLImageElement >
>;

const accountCountry = window.wcpaySettings?.accountStatus?.country || 'US';

/**
 * Maps payment method IDs to their corresponding icon components
 */
function getIconComponent( id: string ): ReactImgFuncComponent {
	const iconMap: Record< string, ReactImgFuncComponent > = {
		affirm: AffirmIcon,
		afterpay_clearpay:
			accountCountry === 'GB' ? ClearpayIcon : AfterpayIcon,
	};
	return iconMap[ id ];
}

/**
 * Maps a PaymentMethodDefinition to a PaymentMethodMapEntry
 */
export function mapDefinitionToEntry(
	def: PaymentMethodDefinition
): PaymentMethodMapEntry {
	return {
		id: def.id,
		label: def.title,
		description: def.description,
		icon: getIconComponent( def.id ),
		currencies: def.currencies,
		stripe_key: def.stripeId,
		allows_manual_capture: def.allowsManualCapture,
		allows_pay_later: def.allowsPayLater,
		accepts_only_domestic_payment: def.acceptsOnlyDomesticPayment,
	};
}
