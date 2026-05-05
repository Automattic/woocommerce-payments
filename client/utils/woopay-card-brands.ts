/**
 * Internal dependencies
 */
import WooPayVisa from 'assets/images/woopay-icons/visa.svg?asset';
import WooPayMastercard from 'assets/images/woopay-icons/mastercard.svg?asset';
import WooPayAmex from 'assets/images/woopay-icons/amex.svg?asset';
import WooPayDiscover from 'assets/images/woopay-icons/discover.svg?asset';
import WooPayJcb from 'assets/images/woopay-icons/jcb.svg?asset';
import WooPayUnionPay from 'assets/images/woopay-icons/unionpay.svg?asset';
import WooPayDiners from 'assets/images/woopay-icons/diners.svg?asset';
import type { CardBrand } from './card-brands';

/**
 * Higher-fidelity WooPay card brand icons.
 * Used by the WooPay express button for preferred card display where
 * icons render at a larger size and need more path detail.
 */
export const wooPayCardBrands: readonly CardBrand[] = [
	{ name: 'visa', component: WooPayVisa },
	{ name: 'mastercard', component: WooPayMastercard },
	{ name: 'amex', component: WooPayAmex },
	{ name: 'discover', component: WooPayDiscover },
	{ name: 'jcb', component: WooPayJcb },
	{ name: 'unionpay', component: WooPayUnionPay },
	{ name: 'diners', component: WooPayDiners },
];
