/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { CachedDispute } from 'wcpay/types/disputes';
import { formatCurrency } from 'wcpay/utils/currency';

interface GetDisputesNoticeStringArgs {
	/**
	 * Active disputes (awaiting a response) to generate the notice string for.
	 */
	activeDisputes: CachedDispute[];
}
export const getDisputesNoticeString = ( {
	activeDisputes,
}: GetDisputesNoticeStringArgs ): string => {
	const disputeCount = activeDisputes.length;

	if ( disputeCount === 1 ) {
		const dispute = activeDisputes[ 0 ];
		return sprintf(
			__( 'Respond to a dispute for %s', 'woocommerce-payments' ),
			formatCurrency( dispute.amount, dispute.currency )
		);
	}
	const disputeTotalAmount = activeDisputes.reduce(
		( total, dispute ) => total + dispute.amount,
		0
	);
	const disputeTotalCurrency = activeDisputes[ 0 ].currency;

	return sprintf(
		__( 'Respond to %d active disputes for %s', 'woocommerce-payments' ),
		disputeCount,
		formatCurrency( disputeTotalAmount, disputeTotalCurrency )
	);
};
