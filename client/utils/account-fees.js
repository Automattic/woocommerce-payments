/** @format */

/**
 * External depencencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';

export const getCurrentFee = ( accountFees ) => {
	return accountFees.discount.length
		? accountFees.discount[ 0 ]
		: accountFees.base;
};

export const formatAccountFeesDescription = ( accountFees ) => {
	const baseFee = accountFees.base;
	const currentFee = getCurrentFee( accountFees );

	let feeDescription = sprintf(
		/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
		__( '%1$.1f%% + %2$s per transaction', 'woocommerce-payments' ),
		baseFee.percentage_rate * 100,
		formatCurrency( baseFee.fixed_rate, baseFee.currency )
	);

	if ( currentFee !== baseFee ) {
		// TODO: Figure out how the UI should work if there are several "discount" fees stacked.
		let percentage, fixed;

		if ( currentFee.discount ) {
			// Proper discount fee (XX% off)
			percentage = baseFee.percentage_rate * ( 1 - currentFee.discount );
			fixed = baseFee.fixed_rate * ( 1 - currentFee.discount );
		} else {
			// Custom base fee (2% + $.20)
			percentage = currentFee.percentage_rate;
			fixed = currentFee.fixed_rate;
		}

		let descriptionString = sprintf(
			/* translators: %1 Base fee (that don't apply to this account at this moment), %2 and %3: Current fee (e.g: 2.9% + $.30) */
			__(
				'<s>%1$s</s> %2$.1f%% + %3$s per transaction',
				'woocommerce-payments'
			),
			feeDescription,
			percentage * 100,
			formatCurrency( fixed, baseFee.currency )
		);

		if ( currentFee.discount ) {
			descriptionString +=
				' ' +
				sprintf(
					/* translators: %d percentage discount to apply */
					__( '(%1$d%% discount)', 'woocommerce-payments' ),
					currentFee.discount * 100
				);
		}

		feeDescription = createInterpolateElement( descriptionString, {
			s: <s />,
		} );
	}

	return feeDescription;
};
