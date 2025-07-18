/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Flex } from 'wcpay/components/wp-components-wrapped/components/flex';
import { FlexItem } from 'wcpay/components/wp-components-wrapped/components/flex-item';

/** Internal dependencies */
import { formatCurrency } from 'multi-currency/interface/functions';
import { formatFeeType } from '../utils';
import { getLocalizedTaxDescription } from '../../utils/tax-descriptions';

interface BreakdownFeeRateProps {
	percentage?: number;
	fixed: number;
	currency: string;
	displayFixedPart?: boolean;
	storeCurrency: string;
}

export const BreakdownFeeRate: React.FC< BreakdownFeeRateProps > = ( {
	percentage,
	fixed,
	currency,
	displayFixedPart,
	storeCurrency,
} ) => {
	const formattedPercentage =
		percentage !== undefined
			? `${ Number.parseFloat( ( percentage * 100 ).toFixed( 2 ) ) }%`
			: '0%';
	const formattedFixed = formatCurrency( fixed, currency, storeCurrency );

	const result = [ formattedPercentage ];
	if ( displayFixedPart || fixed > 0 ) {
		result.push( `${ formattedFixed } ${ storeCurrency }` );
	}

	return <>{ result.filter( ( s ) => s !== '' ).join( ' + ' ) }</>;
};

interface FeeRowProps {
	type: string;
	additionalType?: string;
	percentage?: number;
	fixed: number;
	currency: string;
	isDiscounted?: boolean;
	displayFixedPart?: boolean;
	taxInfo?: {
		description?: string;
		percentage_rate?: number;
	};
	storeCurrency: string;
}

export const FeeRow: React.FC< FeeRowProps > = ( {
	type,
	additionalType,
	percentage,
	fixed,
	currency,
	isDiscounted,
	displayFixedPart,
	taxInfo,
	storeCurrency,
} ) => {
	const formattedFeeType = formatFeeType(
		type,
		additionalType,
		isDiscounted,
		taxInfo
	);
	const feeType = type + ( additionalType ? `_${ additionalType }` : '' );

	return (
		<Flex
			className={ `wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__${ feeType }_fee_info` }
			wrap={ true }
			justify="space-between"
			align="end"
		>
			<FlexItem className="wcpay-transaction-breakdown__fee_name">
				{ formattedFeeType }
			</FlexItem>
			<FlexItem className="wcpay-transaction-breakdown__fee_rate">
				<BreakdownFeeRate
					percentage={ percentage }
					fixed={ fixed }
					currency={ currency }
					displayFixedPart={ displayFixedPart }
					storeCurrency={ storeCurrency }
				/>
			</FlexItem>
		</Flex>
	);
};

interface TaxFeeRowProps {
	description?: string;
	percentageRate?: number;
}

export const TaxFeeRow: React.FC< TaxFeeRowProps > = ( {
	description,
	percentageRate,
} ) => {
	const formattedFeeType = formatFeeType( 'tax', '', false );
	const localizedDescription = description
		? getLocalizedTaxDescription( description )
		: '';

	return (
		<Flex
			className="wcpay-transaction-breakdown__fee_info wcpay-transaction-breakdown__tax_fee_info"
			wrap={ true }
			justify="space-between"
			align="end"
		>
			<FlexItem className="wcpay-transaction-breakdown__fee_name">
				{ formattedFeeType }
			</FlexItem>
			<FlexItem className="wcpay-transaction-breakdown__fee_rate">
				{ localizedDescription }
				{ percentageRate
					? ` ${ ( percentageRate * 100 ).toFixed( 2 ) }%`
					: '' }
			</FlexItem>
		</Flex>
	);
};
