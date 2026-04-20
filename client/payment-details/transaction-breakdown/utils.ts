/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { __ } from '@wordpress/i18n';
import { getLocalizedTaxDescription } from '../utils/tax-descriptions';

export const formatFeeType = (
	type: string,
	additionalType?: string,
	isDiscounted?: boolean,
	taxInfo?: {
		description?: string;
		percentage_rate?: number;
	}
): string => {
	const feeTypes: Record< string, string | Record< string, string > > = {
		total: __( 'Total', 'woocommerce-payments' ),
		base: __( 'Base fee', 'woocommerce-payments' ),
		tax: __( 'Tax on fee', 'woocommerce-payments' ),
		additional: {
			international: __(
				'International payment fee',
				'woocommerce-payments'
			),
			fx: __( 'Currency conversion fee', 'woocommerce-payments' ),
		},
	};

	const suffix = isDiscounted ? ' (discounted)' : '';

	if (
		type === 'tax' &&
		taxInfo?.description &&
		taxInfo?.percentage_rate !== undefined
	) {
		const localizedDescription = getLocalizedTaxDescription(
			taxInfo.description
		);
		const percentage = ( taxInfo.percentage_rate * 100 ).toFixed( 0 );
		return `${ feeTypes.tax } (${ localizedDescription } ${ percentage }%)`;
	}

	if ( type === 'additional' && additionalType ) {
		const additionalFees = feeTypes.additional as Record< string, string >;
		return (
			( additionalFees[ additionalType ] ||
				__( 'Fee', 'woocommerce-payments' ) ) + suffix
		);
	}

	const baseType = feeTypes[ type ];
	return typeof baseType === 'string'
		? baseType + suffix
		: __( 'Fee', 'woocommerce-payments' ) + suffix;
};
