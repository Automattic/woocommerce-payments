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
import { formatCurrency } from 'utils/currency';

interface ConversionIndicatorProps {
	amount: number;
	currency: string;
}

const ConversionIndicator: React.FunctionComponent< ConversionIndicatorProps > = ( {
	amount,
	currency,
} ): React.ReactElement => (
	<Tooltip
		text={ sprintf(
			/* translators: %s is a monetary amount */
			__( 'Converted from %s', 'woocommerce-payments' ),
			formatCurrency( amount, currency )
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

const ConvertedAmount: React.FunctionComponent< ConvertedAmountProps > = ( {
	amount,
	currency,
	fromAmount,
	fromCurrency,
} ): React.ReactElement => {
	const formattedCurrency = formatCurrency( amount, currency );

	// No conversion if currencies match.
	if ( currency === fromCurrency ) {
		return <>{ formattedCurrency }</>;
	}

	return (
		<div className="converted-amount">
			<ConversionIndicator
				amount={ fromAmount }
				currency={ fromCurrency }
			/>
			{ formattedCurrency }
		</div>
	);
};

export default ConvertedAmount;
