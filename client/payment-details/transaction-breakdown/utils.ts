/**
 * External dependencies
 */
import { filter, join } from 'lodash';

/**
 * Internal dependencies
 */
import { __ } from '@wordpress/i18n';
import { formatCurrency } from 'multi-currency/interface/functions';
import { TimelineFeeRate } from 'wcpay/data/timeline/types';

export const formatFeeType = (
	type: string,
	additionalType?: string,
	isDiscounted?: boolean
): string => {
	const feeTypes: Record< string, string | Record< string, string > > = {
		total: __( 'Total transaction fee', 'woocommerce-payments' ),
		base: __( 'Base fee', 'woocommerce-payments' ),
		additional: {
			international: __(
				'International card fee',
				'woocommerce-payments'
			),
			fx: __( 'Currency conversion fee', 'woocommerce-payments' ),
		},
	};

	const suffix = isDiscounted ? ' (discounted)' : '';

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

export const formatFeeRate = (
	percentage: number,
	fixed: number,
	currency: string,
	storeCurrency: string
): string => {
	const formattedPercentage = percentage
		? `${ Number.parseFloat( ( percentage * 100 ).toFixed( 2 ) ) }%`
		: '';
	const formattedFixed = fixed
		? formatCurrency( fixed, currency, storeCurrency )
		: '';

	return (
		join(
			filter( [ formattedPercentage, formattedFixed ], Boolean ),
			' + '
		) || '0%'
	);
};
