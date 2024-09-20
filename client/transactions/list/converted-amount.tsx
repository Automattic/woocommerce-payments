/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Tooltip as FallbackTooltip } from '@wordpress/components';
import SyncIcon from 'gridicons/dist/sync';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'utils/currency';

declare const window: any;

interface ConversionIndicatorProps {
	amount: number;
	currency: string;
	baseCurrency: string;
	fallback?: boolean;
}

const ConversionIndicator = ( {
	amount,
	currency,
	fallback,
	baseCurrency,
}: ConversionIndicatorProps ): React.ReactElement => {
	// If it's available, use the component from WP, not the one within WCPay, as WP's uses an updated component.
	const Tooltip = ! fallback
		? window?.wp?.components?.Tooltip
		: FallbackTooltip;

	return (
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
				style={ { height: '18px', width: '18px' } }
			>
				<SyncIcon size={ 18 } />
			</span>
		</Tooltip>
	);
};

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

	const isUpdatedTooltipAvailable = !! window?.wp?.components?.Tooltip;

	return (
		<div
			className={ classNames(
				'converted-amount',
				! isUpdatedTooltipAvailable && 'converted-amount--fallback'
			) }
		>
			<ConversionIndicator
				amount={ fromAmount }
				currency={ fromCurrency }
				fallback={ ! isUpdatedTooltipAvailable }
				baseCurrency={ currency }
			/>
			{ formattedCurrency }
		</div>
	);
};

export default ConvertedAmount;
