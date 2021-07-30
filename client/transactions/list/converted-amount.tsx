/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Tooltip } from '@wordpress/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'utils/currency';

interface ConversionIndicatorProps {
	amount: number;
	currency: string;
	baseCurrency: string;
}

const ConversionIndicator = ( {
	amount,
	currency,
	baseCurrency,
}: ConversionIndicatorProps ): React.ReactElement => (
	<Tooltip
		text={ sprintf(
			/* translators: %s is a monetary amount */
			__( 'Converted from %s', 'woocommerce-payments' ),
			formatExplicitCurrency( amount, currency, false, baseCurrency )
		) }
		position="bottom center"
	>
		<span
			className="conversion-indicator"
			data-testid="conversion-indicator"
		>
			<Gridicon icon="sync" size={ 18 } />
		</span>
	</Tooltip>
);

interface ConvertedAmountProps {
	amount: number;
	currency: string;
	fromAmount: number;
	fromCurrency: string;
}

const ConvertedAmount = ( {
	amount,
	currency,
	fromAmount,
	fromCurrency,
}: ConvertedAmountProps ): JSX.Element => {
	const formattedCurrency = formatExplicitCurrency( amount, currency );

	// No conversion if currencies match.
	if ( currency === fromCurrency ) {
		return <>{ formattedCurrency }</>;
	}

	return (
		<div className="converted-amount">
			<ConversionIndicator
				amount={ fromAmount }
				currency={ fromCurrency }
				baseCurrency={ currency }
			/>
			{ formattedCurrency }
		</div>
	);
};

export default ConvertedAmount;
