/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type { CachedDispute } from 'wcpay/types/disputes';
import { formatCurrency } from 'wcpay/utils/currency';

export const getDisputesNoticeString = (
	/**
	 * Active disputes (awaiting a response) to generate the notice string for.
	 */
	activeDisputes: CachedDispute[]
): string | null => {
	const disputeCount = activeDisputes.length;

	if ( disputeCount === 0 ) {
		return null;
	}

	if ( disputeCount === 1 ) {
		const dispute = activeDisputes[ 0 ];
		const now = moment();
		const dueBy = moment( dispute.due_by );

		const dueWithin24h =
			dueBy.diff( now, 'hours' ) > 0 && dueBy.diff( now, 'hours' ) <= 24;

		if ( dueWithin24h ) {
			// If the dispute is due within 24 hours, show a more urgent message.
			return sprintf(
				__(
					'Respond to a dispute for %s – last day',
					'woocommerce-payments'
				),
				formatCurrency( dispute.amount, dispute.currency )
			);
		}

		return sprintf(
			__( 'Respond to a dispute for %s', 'woocommerce-payments' ),
			formatCurrency( dispute.amount, dispute.currency )
		);
	}

	// Calculate dispute total per currency.
	const disputeTotalPerCurrency = activeDisputes.reduce(
		( totalPerCurrency, dispute ) => {
			const { currency, amount } = dispute;
			const total = totalPerCurrency[ currency ] || 0;

			return {
				...totalPerCurrency,
				[ currency ]: total + amount,
			};
		},
		{} as Record< string, number >
	);

	// Generate a formatted total amount for each currency: "€10.00, $20.00".
	const disputeTotalAmounts: string = Object.entries(
		disputeTotalPerCurrency
	)
		.map( ( [ currency, amount ] ) => formatCurrency( amount, currency ) )
		.join( ', ' );

	return sprintf(
		__(
			'Respond to %d active disputes for a total of %s',
			'woocommerce-payments'
		),
		disputeCount,
		disputeTotalAmounts
	);
};
