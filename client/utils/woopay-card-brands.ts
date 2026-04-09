/**
 * Internal dependencies
 */
import WoopayVisa from 'assets/images/woopay-icons/visa.svg?asset';
import WoopayMastercard from 'assets/images/woopay-icons/mastercard.svg?asset';
import WoopayAmex from 'assets/images/woopay-icons/amex.svg?asset';
import WoopayDiscover from 'assets/images/woopay-icons/discover.svg?asset';
import WoopayJcb from 'assets/images/woopay-icons/jcb.svg?asset';
import WoopayUnionPay from 'assets/images/woopay-icons/unionpay.svg?asset';

interface CardBrand {
	name: string;
	component: string;
}

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
