/**
 * Internal dependencies
 */
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';
import Jcb from 'assets/images/cards/jcb.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import Cartebancaire from 'assets/images/cards/cartes_bancaires.svg?asset';
import WoopayVisa from 'assets/images/woopay-icons/visa.svg?asset';
import WoopayMastercard from 'assets/images/woopay-icons/mastercard.svg?asset';
import WoopayAmex from 'assets/images/woopay-icons/amex.svg?asset';
import WoopayDiscover from 'assets/images/woopay-icons/discover.svg?asset';
import WoopayJcb from 'assets/images/woopay-icons/jcb.svg?asset';
import WoopayUnionPay from 'assets/images/woopay-icons/unionpay.svg?asset';
import { getUPEConfig } from 'wcpay/utils/checkout';

/**
 * Card brand object interface
 */
interface CardBrand {
	name: string;
	component: string;
}

/**
 * Get card brands array for payment method logos display.
 * Returns standard brands (Visa, MC, Amex, Discover) plus JCB, CUP, and CB (if France).
 *
 * @return {CardBrand[]} Array of card brand objects with name and component properties
 */
export const getCardBrands = (): CardBrand[] => {
	const baseBrands: CardBrand[] = [
		{ name: 'visa', component: Visa },
		{ name: 'mastercard', component: Mastercard },
		{ name: 'amex', component: Amex },
		{ name: 'discover', component: Discover },
	];

	// Always add JCB and CUP
	const additionalBrands: CardBrand[] = [
		{ name: 'jcb', component: Jcb },
		{ name: 'unionpay', component: UnionPay },
	];

	// Add CB (Cartes Bancaires) only for France merchants
	// Try multiple approaches to get the country
	const accountCountry = getUPEConfig( 'storeCountry' );

	if ( accountCountry === 'FR' ) {
		additionalBrands.push( {
			name: 'cartes_bancaires',
			component: Cartebancaire,
		} );
	}

	return [ ...baseBrands, ...additionalBrands ];
};

/**
 * Get card brands with higher-fidelity WooPay icons.
 * Used by the WooPay express button for preferred card display where
 * icons render at a larger size and need more path detail.
 *
 * @return {CardBrand[]} Array of card brand objects with name and component properties
 */
export const getWoopayCardBrands = (): CardBrand[] => {
	const baseBrands: CardBrand[] = [
		{ name: 'visa', component: WoopayVisa },
		{ name: 'mastercard', component: WoopayMastercard },
		{ name: 'amex', component: WoopayAmex },
		{ name: 'discover', component: WoopayDiscover },
	];

	const additionalBrands: CardBrand[] = [
		{ name: 'jcb', component: WoopayJcb },
		{ name: 'unionpay', component: WoopayUnionPay },
	];

	return [ ...baseBrands, ...additionalBrands ];
};
