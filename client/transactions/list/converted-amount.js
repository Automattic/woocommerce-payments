/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Tooltip } from '@wordpress/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';

const ConversionIndicator = ( { amount, currency } ) => (
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

const ConvertedAmount = ( { amount, currency, fromAmount, fromCurrency } ) => {
	const formattedCurrency = formatCurrency( amount, currency );

	// No conversion if currencies match.
	if ( currency === fromCurrency ) {
		return formattedCurrency;
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
