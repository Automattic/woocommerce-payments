/** @format */
/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { dateI18n } from '@wordpress/date';
import { sprintf, __ } from '@wordpress/i18n';
import moment from 'moment';

/**
 * Internal dependencies
 */
import CurrencyPreview from './currency-preview';
import './style.scss';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped/components/external-link';
import { Button } from 'wcpay/components/wp-components-wrapped/components/button';
import { Card } from 'wcpay/components/wp-components-wrapped/components/card';
import { CardBody } from 'wcpay/components/wp-components-wrapped/components/card-body';
import { SelectControl } from 'wcpay/components/wp-components-wrapped/components/select-control';
import { TextControl } from 'wcpay/components/wp-components-wrapped/components/text-control';
import { RadioControl } from 'wcpay/components/wp-components-wrapped/components/radio-control';
import {
	decimalCurrencyCharmOptions,
	decimalCurrencyRoundingOptions,
	zeroDecimalCurrencyCharmOptions,
	zeroDecimalCurrencyRoundingOptions,
} from './constants';
import {
	useCurrencies,
	useCurrencySettings,
	useEnabledCurrencies,
	useStoreSettings,
} from 'multi-currency/data';
import MultiCurrencySettingsContext from 'multi-currency/context';
import {
	LoadableBlock,
	SettingsLayout,
	SettingsSection,
} from 'multi-currency/interface/components';
import interpolateComponents from '@automattic/interpolate-components';

const CurrencySettingsDescription = () => (
	<>
		<h2>{ __( 'Currency settings', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Choose between automatic or manual exchange ' +
					'rates and modify formatting rules to refine the ' +
					'display of your prices.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const SingleCurrencySettings = () => {
	const {
		currencyCodeToShowSettingsFor: currency,
		setCurrencyCodeToShowSettingsFor,
	} = useContext( MultiCurrencySettingsContext );

	const [ isDirty, setIsDirty ] = useState( false );

	const { currencies } = useCurrencies();
	const { enabledCurrencies } = useEnabledCurrencies();
	const { storeSettings } = useStoreSettings();

	const {
		currencySettings,
		isLoading,
		submitCurrencySettings,
	} = useCurrencySettings( currency );

	const storeCurrency = currencies.default ? currencies.default : {};
	const targetCurrency = currencies.available
		? currencies.available[ currency ]
		: {};

	const targetCurrencyRoundingOptions = targetCurrency.is_zero_decimal
		? zeroDecimalCurrencyRoundingOptions
		: decimalCurrencyRoundingOptions;

	const targetCurrencyCharmOptions = targetCurrency.is_zero_decimal
		? zeroDecimalCurrencyCharmOptions
		: decimalCurrencyCharmOptions;

	const initialExchangeRateType = 'automatic';
	const initialManualRate = 0;
	const initialPriceRoundingType = targetCurrency?.is_zero_decimal
		? '100'
		: '1.00';
	const initialPriceCharmType = '0.00';

	const [ exchangeRateType, setExchangeRateType ] = useState(
		initialExchangeRateType
	);
	const [ manualRate, setManualRate ] = useState( initialManualRate );
	const [ priceRoundingType, setPriceRoundingType ] = useState(
		initialPriceRoundingType
	);
	const [ priceCharmType, setPriceCharmType ] = useState(
		initialPriceCharmType
	);
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		if ( currencySettings[ currency ] ) {
			setExchangeRateType(
				currencySettings[ currency ].exchange_rate_type ||
					initialExchangeRateType
			);
			setManualRate(
				currencySettings[ currency ].manual_rate || initialManualRate
			);
			setPriceRoundingType(
				currencySettings[ currency ].price_rounding ??
					initialPriceRoundingType
			);
			setPriceCharmType(
				currencySettings[ currency ].price_charm ||
					initialPriceCharmType
			);
		}
	}, [ currencySettings, currency, initialPriceRoundingType ] );

	const dateFormat = storeSettings.date_format ?? 'M j, Y';
	const timeFormat = storeSettings.time_format ?? 'g:iA';

	const formattedLastUpdatedDateTime = targetCurrency
		? dateI18n(
				`${ dateFormat } ${ timeFormat }`,
				moment.unix( targetCurrency.last_updated ).toISOString()
		  )
		: '';
	const CurrencyPreviewDescription = () => (
		<>
			<h2>{ __( 'Preview', 'woocommerce-payments' ) }</h2>
			<p>
				{ ! isLoading
					? sprintf(
							__(
								'Enter a price in your default currency (%s) to ' +
									'see it converted to %s using the ' +
									'exchange rate and formatting rules above.',
								'woocommerce-payments'
							),
							storeCurrency.name,
							targetCurrency.name
					  )
					: '' }
			</p>
		</>
	);

	const saveSingleCurrencySettings = () => {
		setIsSaving( true );
		submitCurrencySettings( targetCurrency.code, {
			exchange_rate_type: exchangeRateType,
			manual_rate: manualRate,
			price_rounding: priceRoundingType,
			price_charm: priceCharmType,
		} );

		// Update the rate to display it in the Currency list if is set as manual
		if ( ! isNaN( manualRate ) ) {
			enabledCurrencies[ targetCurrency.code ].rate = Number(
				manualRate
			);
		}

		setIsSaving( false );
		setIsDirty( false );
	};

	return (
		<div className="single-currency-settings">
			<SettingsLayout>
				<h2 className="single-currency-settings-breadcrumb">
					<Button
						isLink
						onClick={ () =>
							setCurrencyCodeToShowSettingsFor( null )
						}
						__next40pxDefaultSize
					>
						{ __( 'Enabled currencies', 'woocommerce-payments' ) }
					</Button>{ ' ' }
					&gt; { targetCurrency.name } ({ targetCurrency.code }){ ' ' }
					{ targetCurrency.flag }
				</h2>
				<SettingsSection description={ CurrencySettingsDescription }>
					<LoadableBlock isLoading={ isLoading } numLines={ 33 }>
						<Card className="single-currency-settings-currency-settings">
							<CardBody className="wcpay-card-body">
								<h4>
									{ __(
										'Exchange rate',
										'woocommerce-payments'
									) }
								</h4>

								<fieldset>
									<ul>
										<li>
											<RadioControl
												onChange={ ( value ) => {
													setExchangeRateType(
														value
													);
													setIsDirty( true );
													if ( value === 'manual' ) {
														setManualRate(
															manualRate
																? manualRate
																: targetCurrency.rate
														);
													}
												} }
												options={ [
													{
														description: __(
															targetCurrency.last_updated
																? sprintf(
																		__(
																			'Current rate: 1 %s = %s %s (Last updated: %s)',
																			'woocommerce-payments'
																		),
																		storeCurrency.code,
																		targetCurrency.rate,
																		targetCurrency.code,
																		formattedLastUpdatedDateTime
																  )
																: __(
																		'Error - Unable to fetch automatic rate for this currency'
																  ),
															'woocommerce-payments'
														),
														label: __(
															'Fetch rates automatically',
															'woocommerce-payments'
														),
														value: 'automatic',
													},
													{
														description: __(
															'Enter your fixed rate of exchange',
															'woocommerce-payments'
														),
														label: __(
															'Manual',
															'woocommerce-payments'
														),
														value: 'manual',
													},
												] }
												selected={ exchangeRateType }
											/>
										</li>
										{ exchangeRateType === 'manual' && (
											<li>
												<TextControl
													data-testid="manual_rate_input"
													label={ __(
														'Manual Rate',
														'woocommerce-payments'
													) }
													help={ __(
														'Enter the manual rate you would like to use. Must be a positive number.',
														'woocommerce-payments'
													) }
													value={
														manualRate
															? manualRate
															: targetCurrency.rate
													}
													onChange={ ( value ) => {
														setManualRate(
															value.replace(
																/,/g,
																'.'
															)
														);
														setIsDirty( true );
													} }
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
											</li>
										) }
									</ul>
								</fieldset>
								<h4>
									{ __(
										'Formatting rules',
										'woocommerce-payments'
									) }
								</h4>
								<fieldset>
									<ul>
										<li>
											<SelectControl
												data-testid="price_rounding"
												label={ __(
													'Price rounding',
													'woocommerce-payments'
												) }
												help={ interpolateComponents( {
													mixedString: sprintf(
														__(
															"Make your %s prices consistent by rounding them up after they're converted. {{learnMoreLink}}Learn more{{/learnMoreLink}}",
															'woocommerce-payments'
														),
														targetCurrency.code
													),
													components: {
														learnMoreLink: (
															<ExternalLink href="https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/#price-rounding" />
														),
													},
												} ) }
												value={ parseFloat(
													priceRoundingType
												) }
												onChange={ ( value ) => {
													setPriceRoundingType(
														value
													);
													setIsDirty( true );
												} }
												options={ Object.keys(
													targetCurrencyRoundingOptions
												).map( ( value ) => ( {
													value: parseFloat( value ),
													label:
														targetCurrencyRoundingOptions[
															value
														],
												} ) ) }
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										</li>
										<li>
											<SelectControl
												data-testid="price_charm"
												label={ __(
													'Charm pricing',
													'woocommerce-payments'
												) }
												help={ interpolateComponents( {
													mixedString: sprintf(
														__(
															'Reduce the converted price for a specific amount. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
															'woocommerce-payments'
														),
														targetCurrency.code
													),
													components: {
														learnMoreLink: (
															<ExternalLink href="https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/#charm-pricing" />
														),
													},
												} ) }
												value={ parseFloat(
													priceCharmType
												) }
												onChange={ ( value ) => {
													setPriceCharmType( value );
													setIsDirty( true );
												} }
												options={ Object.keys(
													targetCurrencyCharmOptions
												).map( ( value ) => ( {
													value: parseFloat( value ),
													label:
														targetCurrencyCharmOptions[
															value
														],
												} ) ) }
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										</li>
									</ul>
								</fieldset>
							</CardBody>
						</Card>
					</LoadableBlock>
				</SettingsSection>
				<SettingsSection description={ CurrencyPreviewDescription }>
					<LoadableBlock isLoading={ isLoading } numLines={ 8 }>
						<CurrencyPreview
							className="single-currency-settings-currency-preview"
							storeCurrency={ storeCurrency }
							targetCurrency={ targetCurrency }
							currencyRate={
								exchangeRateType === 'manual'
									? manualRate
									: null
							}
							roundingValue={ priceRoundingType }
							charmValue={ priceCharmType }
						/>
					</LoadableBlock>
				</SettingsSection>
				<SettingsSection className="single-currency-settings-save-settings-section">
					<Button
						isPrimary
						isBusy={ isSaving }
						disabled={ isSaving || ! isDirty }
						onClick={ saveSingleCurrencySettings }
						__next40pxDefaultSize
					>
						{ __( 'Save changes', 'woocommerce-payments' ) }
					</Button>
				</SettingsSection>
			</SettingsLayout>
		</div>
	);
};

export default SingleCurrencySettings;
