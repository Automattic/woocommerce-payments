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
import { formatFee } from 'utils/fees';

export const getCurrentFee = ( accountFees ) => {
	return accountFees.discount.length
		? accountFees.discount[ 0 ]
		: accountFees.base;
};

const getDefaultFeeFormat = () => {
	/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
	return __( '%1$f%% + %2$s per transaction', 'woocommerce-payments' );
};

const getDefaultDiscountFormat = () => {
	/* translators: %f percentage discount to apply */
	return __( '(%f%% discount)', 'woocommerce-payments' );
};

export const formatAccountFeesDescription = (
	accountFees,
	format = getDefaultFeeFormat(),
	discountFormat = getDefaultDiscountFormat()
) => {
	const baseFee = accountFees.base;
	const currentFee = getCurrentFee( accountFees );

	let feeDescription = sprintf(
		format,
		formatFee( baseFee.percentage_rate ),
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
			// eslint-disable-next-line max-len
			/* translators: %1 Base fee (that don't apply to this account at this moment), %2: Current fee (e.g: "2.9% + $.30 per transaction") */
			__( '<s>%1$s</s> %2$s', 'woocommerce-payments' ),
			feeDescription,
			sprintf(
				format,
				formatFee( percentage ),
				formatCurrency( fixed, baseFee.currency )
			)
		);

		if ( currentFee.discount && 0 < discountFormat.length ) {
			descriptionString +=
				' ' +
				sprintf( discountFormat, formatFee( currentFee.discount ) );
		}

		feeDescription = createInterpolateElement( descriptionString, {
			s: <s />,
		} );
	}

	return feeDescription;
};

export const formatMethodFeesDescription = ( methodFees ) => {
	if ( ! methodFees ) {
		return __( 'missing fees', 'woocommerce-payments' );
	}

	/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
	const format = __( '%1$f%% + %2$s', 'woocommerce-payments' );

	return formatAccountFeesDescription( methodFees, format, '' );
};
