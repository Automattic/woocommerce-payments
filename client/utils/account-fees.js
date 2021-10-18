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

export const formatAccountFeesDescription = (
	accountFees,
	customFormats = {}
) => {
	const baseFee = accountFees.base;
	const currentFee = getCurrentFee( accountFees );

	// Default formats will be used if no matching field was passed in the `formats` parameter.
	const formats = {
		/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
		fee: __( '%1$f%% + %2$s per transaction', 'woocommerce-payments' ),
		/* translators: %f percentage discount to apply */
		discount: __( '(%f%% discount)', 'woocommerce-payments' ),
		displayBaseFeeIfDifferent: true,
		...customFormats,
	};

	let feeDescription = sprintf(
		formats.fee,
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

		let currentFeeDescription = sprintf(
			formats.fee,
			formatFee( percentage ),
			formatCurrency( fixed, baseFee.currency )
		);

		if ( formats.displayBaseFeeIfDifferent ) {
			currentFeeDescription = sprintf(
				// eslint-disable-next-line max-len
				/* translators: %1 Base fee (that don't apply to this account at this moment), %2: Current fee (e.g: "2.9% + $.30 per transaction") */
				__( '<s>%1$s</s> %2$s', 'woocommerce-payments' ),
				feeDescription,
				currentFeeDescription
			);
		}

		if ( currentFee.discount && 0 < formats.discount.length ) {
			currentFeeDescription +=
				' ' +
				sprintf( formats.discount, formatFee( currentFee.discount ) );
		}

		feeDescription = createInterpolateElement( currentFeeDescription, {
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

	return formatAccountFeesDescription( methodFees, {
		fee: format,
		discount: '',
		displayBaseFeeIfDifferent: false,
	} );
};

export const getTransactionsPaymentMethodName = ( paymentMethod ) => {
	switch ( paymentMethod ) {
		case 'card':
			return __( 'Card transactions', 'woocommerce-payments' );
		case 'card_present':
			return __( 'In-person transactions', 'woocommerce-payments' );
		case 'giropay':
			return __( 'GiroPay transactions', 'woocommerce-payments' );
		case 'sofort':
			return __( 'Sofort transactions', 'woocommerce-payments' );
		default:
			return __( 'Unknown transactions', 'woocommerce-payments' );
	}
};
