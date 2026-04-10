/**
 * Internal dependencies
 */
import WoopayVisa from 'assets/images/woopay-icons/visa.svg?asset';
import WoopayMastercard from 'assets/images/woopay-icons/mastercard.svg?asset';
import WoopayAmex from 'assets/images/woopay-icons/amex.svg?asset';
import WoopayDiscover from 'assets/images/woopay-icons/discover.svg?asset';
import WoopayJcb from 'assets/images/woopay-icons/jcb.svg?asset';
import WoopayUnionPay from 'assets/images/woopay-icons/unionpay.svg?asset';
import WoopayDiners from 'assets/images/woopay-icons/diners.svg?asset';
import type { CardBrand } from './card-brands';

/**
 * Higher-fidelity WooPay card brand icons.
 * Used by the WooPay express button for preferred card display where
 * icons render at a larger size and need more path detail.
 *
 * Static array — the brand list does not change at runtime.
 */
const woopayCardBrands: CardBrand[] = [
	{ name: 'visa', component: WoopayVisa },
	{ name: 'mastercard', component: WoopayMastercard },
	{ name: 'amex', component: WoopayAmex },
	{ name: 'discover', component: WoopayDiscover },
	{ name: 'jcb', component: WoopayJcb },
	{ name: 'unionpay', component: WoopayUnionPay },
	{ name: 'diners', component: WoopayDiners },
];

export const getWoopayCardBrands = (): CardBrand[] => woopayCardBrands;
