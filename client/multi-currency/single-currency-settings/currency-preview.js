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
	const [ baseValue, setBaseValue ] = useState( 20.0 );
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
			const amount = parseFloat( value.toString().replace( /,/g, '.' ) );
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
			const initialCalculation = calculateCurrencyConversion( baseValue );
			setCalculatedValue( initialCalculation );
		}
	}, [
		calculateCurrencyConversion,
		baseValue,
		targetCurrency,
		roundingValue,
		charmValue,
		currencyRate,
	] );

	const handleTextControlChange = ( value ) => {
		setBaseValue( value );
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
								data-testid="store_currency_value"
								value={ baseValue.toString() }
								onChange={ handleTextControlChange }
							/>
						) : (
							<TextControlWithAffixes
								suffix={ storeCurrency.symbol }
								data-testid="store_currency_value"
								value={ baseValue.toString() }
								onChange={ handleTextControlChange }
							/>
						) }
					</div>
					<div>
						<h4>{ targetCurrency && targetCurrency.name }</h4>
						<div>
							<strong data-testid="calculated_value">
								{ calculatedValue }
							</strong>
						</div>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default CurrencyPreview;
