/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import type { PillType } from 'components/pill';

export type TransactionStatus = 'allow' | 'review' | 'block';
type TransactionStatusMapping = Record<
	TransactionStatus,
	{
		type: PillType;
		message: string;
	}
>;
const transactionStatusMapping: TransactionStatusMapping = {
	allow: {
		type: 'success',
		message: __( 'Succeeded', 'woocommerce-payments' ),
	},
	review: {
		type: 'alert',
		message: __( 'Needs review', 'woocommerce-payments' ),
	},
	block: {
		type: 'danger',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
};

export default transactionStatusMapping;
