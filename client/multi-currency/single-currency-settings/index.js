/** @format */
/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import SettingsLayout from 'wcpay/settings/settings-layout';
import SettingsSection from 'wcpay/settings/settings-section';
import moment from 'moment';

/**
 * Internal dependencies
 */
import CurrencyPreview from './currency-preview';
import './style.scss';
import { Button, Card, CardBody } from '@wordpress/components';
import classNames from 'classnames';
import {
	decimalCurrencyCharmOptions,
	decimalCurrencyRoundingOptions,
	zeroDecimalCurrencyCharmOptions,
	zeroDecimalCurrencyRoundingOptions,
	toMoment,
} from './constants';
import {
	useCurrencies,
	useCurrencySettings,
	useEnabledCurrencies,
	useStoreSettings,
} from 'wcpay/data';
import MultiCurrencySettingsContext from '../context';
import { LoadableBlock } from 'wcpay/components/loadable';

const SingleCurrencySettings = () => {
	const {
		currencyCodeToShowSettingsFor,
		closeSingleCurrencySettings,
	} = useContext( MultiCurrencySettingsContext );

	const currency = currencyCodeToShowSettingsFor;

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

	// Polyfill for window.wcpaySettings.zeroDecimalCurrencies.
	// It's needed for wcpay/currency/utils library to format currencies correctly.
	window.wcpaySettings = window.wcpaySettings || {
		zeroDecimalCurrencies: Object.values( currencies.available )
			.filter( ( currencyInfo ) => currencyInfo.is_zero_decimal )
			.map( ( currencyInfo ) => currencyInfo.code ),
	};

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

	const formattedLastUpdatedDateTime = targetCurrency
		? moment
				.unix( targetCurrency.last_updated )
				.format(
					toMoment( storeSettings.date_format ?? 'F j, Y' ) +
						' ' +
						toMoment( storeSettings.time_format ?? 'HH:mm' )
				)
		: '';

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
	};

	return (
		<div className={ 'single-currency-settings' }>
			<SettingsLayout displayBanner={ false }>
				<h2 className={ 'single-currency-settings-breadcrumb' }>
					<Button
						isLink
						onClick={ () => closeSingleCurrencySettings() }
					>
						{ __( 'Enabled currencies', 'woocommerce-payments' ) }
					</Button>{ ' ' }
					&gt; { targetCurrency.name } ({ targetCurrency.code }){ ' ' }
					{ targetCurrency.flag }
				</h2>
				<SettingsSection Description={ CurrencySettingsDescription }>
					<LoadableBlock isLoading={ isLoading } numLines={ 33 }>
						<Card
							className={
								'single-currency-settings-currency-settings'
							}
						>
							<CardBody>
								<h3>
									{ __(
										'Exchange rate',
										'woocommerce-payments'
									) }
								</h3>
								<fieldset>
									<ul>
										<li>
											<label htmlFor="single-currency-settings__automatic_rate_radio">
												<div
													className={
														'single-currency-settings-radio-wrapper'
													}
												>
													<input
														name={
															'wcpay_multi_currency_exchange_rate_aud'
														}
														id={
															'single-currency-settings__automatic_rate_radio'
														}
														value={ 'automatic' }
														type={ 'radio' }
														className={
															'exchange-rate-selector'
														}
														checked={
															'automatic' ===
															exchangeRateType
														}
														onChange={ () =>
															setExchangeRateType(
																'automatic'
															)
														}
													/>
													<h4>
														{ __(
															'Fetch rates automatically',
															'woocommerce-payments'
														) }
													</h4>
												</div>
												<p
													className={ classNames(
														'single-currency-settings-description',
														'single-currency-settings-description-inset'
													) }
												>
													{ targetCurrency.last_updated
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
														  ) }
												</p>
											</label>
										</li>
										<li>
											<label
												htmlFor={
													'single-currency-settings__manual_rate_radio'
												}
											>
												<div
													className={
														'single-currency-settings-radio-wrapper'
													}
												>
													<input
														name={
															'wcpay_multi_currency_exchange_rate_aud'
														}
														id={
															'single-currency-settings__manual_rate_radio'
														}
														value={ 'manual' }
														type={ 'radio' }
														className={
															'exchange-rate-selector'
														}
														checked={
															'manual' ===
															exchangeRateType
														}
														onChange={ () =>
															setExchangeRateType(
																'manual'
															)
														}
													/>
													<h4>
														{ __(
															'Manual',
															'woocommerce-payments'
														) }
													</h4>
												</div>
												<p
													className={ classNames(
														'single-currency-settings-description',
														'single-currency-settings-description-inset'
													) }
												>
													{ __(
														'Enter your fixed rate of exchange',
														'woocommerce-payments'
													) }
												</p>
											</label>
										</li>
										{ 'manual' === exchangeRateType ? (
											<li>
												<h4>
													{ __(
														'Manual Rate',
														'woocommerce-payments'
													) }
												</h4>
												<input
													type="text"
													data-testid={
														'manual_rate_input'
													}
													defaultValue={
														manualRate
															? manualRate
															: targetCurrency.rate
													}
													onChange={ ( event ) =>
														setManualRate(
															event.target.value.replace(
																/,/g,
																'.'
															)
														)
													}
												/>
												<p
													className={
														'single-currency-settings-description'
													}
												>
													{ __(
														'Enter the manual rate you would like to use. Must be a positive number.',
														'woocommerce-payments'
													) }
												</p>
											</li>
										) : (
											''
										) }
									</ul>
								</fieldset>
								<h3>
									{ __(
										'Formatting rules',
										'woocommerce-payments'
									) }
								</h3>
								<fieldset>
									<ul>
										<li>
											<h4>
												{ __(
													'Price rounding',
													'woocommerce-payments'
												) }
											</h4>
											{ /* eslint-disable jsx-a11y/no-onchange */ }
											<select
												value={ parseFloat(
													priceRoundingType
												) }
												data-testid={ 'price_rounding' }
												onChange={ ( event ) =>
													setPriceRoundingType(
														event.target.value
													)
												}
											>
												{ /* eslint-enable jsx-a11y/no-onchange */ }
												{ Object.keys(
													targetCurrencyRoundingOptions
												).map( ( value ) => {
													return (
														<option
															value={ parseFloat(
																value
															) }
															key={ value }
														>
															{
																targetCurrencyRoundingOptions[
																	value
																]
															}
														</option>
													);
												} ) }
											</select>
											<p
												className={
													'single-currency-settings-description'
												}
											>
												{ sprintf(
													__(
														"Make your %s prices consistent by rounding them up after they're converted.",
														'woocommerce-payments'
													),
													targetCurrency.code
												) }
												<Button
													isLink
													onClick={ () => {
														open(
															'http://woocommerce.com/document/payments/' +
																'currencies/multi-currency-setup/#price-rounding',
															'_blank'
														);
													} }
												>
													{ __(
														'Learn more',
														'woocommerce-payments'
													) }
												</Button>
											</p>
										</li>
										<li>
											<h4>
												{ __(
													'Charm pricing',
													'woocommerce-payments'
												) }
											</h4>
											{ /* eslint-disable jsx-a11y/no-onchange */ }
											<select
												value={ parseFloat(
													priceCharmType
												) }
												data-testid={ 'price_charm' }
												onChange={ ( event ) =>
													setPriceCharmType(
														event.target.value
													)
												}
											>
												{ /* eslint-enable jsx-a11y/no-onchange */ }
												{ Object.keys(
													targetCurrencyCharmOptions
												).map( ( value ) => {
													return (
														<option
															value={ parseFloat(
																value
															) }
															key={ value }
														>
															{
																targetCurrencyCharmOptions[
																	value
																]
															}
														</option>
													);
												} ) }
											</select>
											<p
												className={
													'single-currency-settings-description'
												}
											>
												{ __(
													'Reduce the converted price for a specific amount',
													'woocommerce-payments'
												) }
												<Button
													isLink
													onClick={ () => {
														open(
															'http://woocommerce.com/document/payments/' +
																'currencies/multi-currency-setup/#price-charm',
															'_blank'
														);
													} }
												>
													{ __(
														'Learn more',
														'woocommerce-payments'
													) }
												</Button>
											</p>
										</li>
									</ul>
								</fieldset>
							</CardBody>
						</Card>
					</LoadableBlock>
				</SettingsSection>
				<SettingsSection Description={ CurrencyPreviewDescription }>
					<LoadableBlock isLoading={ isLoading } numLines={ 8 }>
						<CurrencyPreview
							className={
								'single-currency-settings-currency-preview'
							}
							storeCurrency={ storeCurrency }
							targetCurrency={ targetCurrency }
							currencyRate={
								'manual' === exchangeRateType
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
						disabled={ isSaving }
						onClick={ saveSingleCurrencySettings }
					>
						{ __( 'Save changes', 'woocommerce-payments' ) }
					</Button>
				</SettingsSection>
			</SettingsLayout>
		</div>
	);
};

export default SingleCurrencySettings;
