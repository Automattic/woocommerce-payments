/** @format */
/**
 * External dependencies
 */
import React, { useCallback, useEffect, useState } from 'react';
import { sprintf } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { TextControlWithAffixes } from '@woocommerce/components';

const CurrencyPreview = ( {
	storeCurrency,
	targetCurrency,
	currencyRate = null, // Manual rate value.
	roundingValue,
	charmValue,
} ) => {
	const initialValue = '20.00';

	const [ calculatedValue, setCalculatedValue ] = useState( 0 );

	const formatCurrency = ( amount, currencyInfo ) => {
		const formatMap = {
			right: '%1$s%2$s',
			right_space: '%1$s %2$s',
			left: '%2$s%1$s',
			left_space: '%2$s%1$s',
		};
		const currencyFormat = Object.keys( formatMap ).includes(
			currencyInfo.symbol_position
		)
			? formatMap[ currencyInfo.symbol_position ]
			: '%1$s%2$s';
		return sprintf(
			currencyFormat,
			amount.toFixed( currencyInfo.is_zero_decimal ? 0 : 2 ),
			currencyInfo.symbol
		);
	};

	const calculateCurrencyConversion = useCallback(
		( value ) => {
			const amount = parseFloat( value );
			const converted =
				amount *
				parseFloat( currencyRate ? currencyRate : targetCurrency.rate );
			const rounded =
				'none' === roundingValue
					? converted
					: converted - ( converted % parseFloat( roundingValue ) );
			const charmed = rounded + parseFloat( charmValue );
			return formatCurrency( charmed, targetCurrency );
		},
		[ charmValue, currencyRate, roundingValue, targetCurrency ]
	);

	useEffect( () => {
		if ( targetCurrency ) {
			const initialCalculation = calculateCurrencyConversion(
				initialValue
			);
			setCalculatedValue( initialCalculation );
		}
	}, [
		calculateCurrencyConversion,
		initialValue,
		targetCurrency,
		roundingValue,
		charmValue,
		currencyRate,
	] );

	const handleTextControlChange = ( value ) => {
		const calculatedNewValue = calculateCurrencyConversion( value );
		setCalculatedValue( calculatedNewValue );
	};

	return (
		<div className={ 'single-currency-settings-preview-wrapper' }>
			<Card>
				<CardBody>
					<div>
						<h4>{ storeCurrency.name }</h4>
						{ 'left' === storeCurrency.symbol_position ? (
							<TextControlWithAffixes
								prefix={ storeCurrency.symbol }
								defaultValue={ initialValue }
								onChange={ handleTextControlChange }
							/>
						) : (
							<TextControlWithAffixes
								suffix={ storeCurrency.symbol }
								defaultValue={ initialValue }
								onChange={ handleTextControlChange }
							/>
						) }
					</div>
					<div>
						<h4>{ targetCurrency && targetCurrency.name }</h4>
						<div>
							<strong>{ calculatedValue }</strong>
						</div>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default CurrencyPreview;
