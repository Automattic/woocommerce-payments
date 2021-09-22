/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { Button, Card, CardBody, CheckboxControl } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';

import { useStoreSettings } from 'wcpay/data';
import SettingsSection from 'wcpay/settings/settings-section';
import { createInterpolateElement } from '@wordpress/element';
import { LoadableBlock } from 'wcpay/components/loadable';

const StoreSettingsDescription = () => {
	const LEARN_MORE_URL =
		'https://docs.woocommerce.com/document/payments/currencies/multi-currency-setup/';

	return (
		<>
			<h2>{ __( 'Store settings', 'woocommerce-payments' ) }</h2>
			<p>
				{ createInterpolateElement(
					sprintf(
						__(
							'Store settings allow your customers to choose which currency they ' +
								'would like to use when shopping at your store. <learnMoreLink>' +
								'Learn more</learnMoreLink>',
							'woocommerce-payments'
						),
						LEARN_MORE_URL
					),
					{
						learnMoreLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href={ LEARN_MORE_URL }
								target={ '_blank' }
								rel={ 'noreferrer' }
							/>
						),
					}
				) }
			</p>
		</>
	);
};

const StoreSettings = () => {
	const {
		storeSettings,
		isLoading,
		submitStoreSettingsUpdate,
	} = useStoreSettings();
	const [ isSavingSettings, setIsSavingSettings ] = useState( false );
	const [
		isAutomaticSwitchEnabledValue,
		setIsAutomaticSwitchEnabledValue,
	] = useState( false );

	const [
		isStorefrontSwitcherEnabledValue,
		setIsStorefrontSwitcherEnabledValue,
	] = useState( false );

	useEffect( () => {
		if ( Object.keys( storeSettings ).length ) {
			setIsStorefrontSwitcherEnabledValue(
				storeSettings.enable_storefront_switcher
			);
			setIsAutomaticSwitchEnabledValue(
				storeSettings.enable_auto_currency
			);
		}
	}, [
		setIsAutomaticSwitchEnabledValue,
		setIsStorefrontSwitcherEnabledValue,
		storeSettings,
	] );

	const handleIsAutomaticSwitchEnabledClick = ( value ) => {
		setIsAutomaticSwitchEnabledValue( value );
	};

	const handleIsStorefrontSwitcherEnabledClick = ( value ) => {
		setIsStorefrontSwitcherEnabledValue( value );
	};

	const saveSettings = () => {
		setIsSavingSettings( true );
		submitStoreSettingsUpdate(
			isAutomaticSwitchEnabledValue,
			isStorefrontSwitcherEnabledValue
		);
		setIsSavingSettings( false );
	};

	return (
		<>
			<SettingsSection
				Description={ StoreSettingsDescription }
				className={ 'multi-currency-settings-store-settings-section' }
			>
				<LoadableBlock isLoading={ isLoading } numLines={ 10 }>
					<Card className="multi-currency-settings__wrapper">
						<CardBody>
							<CheckboxControl
								checked={ isAutomaticSwitchEnabledValue }
								onChange={ handleIsAutomaticSwitchEnabledClick }
								data-testid={ 'enable_auto_currency' }
								label={ __(
									'Automatically switch customers to their local currency if it has been enabled',
									'woocommerce-payments'
								) }
							/>
							<div className="multi-currency-settings__description">
								{ __(
									'Customers will be notified via store alert banner.',
									'woocommerce-payments'
								) }
							</div>
							{ 'Storefront' === storeSettings.site_theme ? (
								<CheckboxControl
									checked={ isStorefrontSwitcherEnabledValue }
									onChange={
										handleIsStorefrontSwitcherEnabledClick
									}
									data-testid={ 'enable_storefront_switcher' }
									label={ __(
										'Add a currency switcher to the cart widget',
										'woocommerce-payments'
									) }
								/>
							) : null }
							<div className="multi-currency-settings__description">
								{ __(
									'A currency switcher is also available in your widgets.',
									'woocommerce-payments'
								) }
							</div>
						</CardBody>
					</Card>
				</LoadableBlock>
			</SettingsSection>
			<SettingsSection className="multi-currency-settings-save-settings-section">
				<Button
					isPrimary
					isBusy={ isSavingSettings }
					disabled={ isSavingSettings }
					onClick={ saveSettings }
				>
					{ __( 'Save changes', 'woocommerce-payments' ) }
				</Button>
			</SettingsSection>
		</>
	);
};

export default StoreSettings;
