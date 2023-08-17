/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import type { ChipType } from 'components/chip';

export type TransactionStatus = 'allow' | 'review' | 'block';
type TransactionStatusMapping = Record<
	TransactionStatus,
	{
		type: ChipType;
		message: string;
	}
>;
const transactionStatusMapping: TransactionStatusMapping = {
	allow: {
		type: 'primary',
		message: __( 'Succeeded', 'woocommerce-payments' ),
	},
	review: {
		type: 'warning',
		message: __( 'Needs review', 'woocommerce-payments' ),
	},
	block: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
};

export default transactionStatusMapping;
