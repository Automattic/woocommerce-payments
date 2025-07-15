/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { Button } from 'wcpay/components/wp-components-wrapped/components/button';
import { Card } from 'wcpay/components/wp-components-wrapped/components/card';
import { CardBody } from 'wcpay/components/wp-components-wrapped/components/card-body';
import { CheckboxControl } from 'wcpay/components/wp-components-wrapped/components/checkbox-control';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped/components/external-link';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useStoreSettings } from 'multi-currency/data';
import {
	LoadableBlock,
	SettingsSection,
} from 'multi-currency/interface/components';
import PreviewModal from 'multi-currency/components/preview-modal';

const StoreSettingsDescription = () => (
	<>
		<h2>{ __( 'Store settings', 'woocommerce-payments' ) }</h2>
		<p>
			{ createInterpolateElement(
				__(
					'Store settings allow your customers to choose which currency they ' +
						'would like to use when shopping at your store. <learnMoreLink>' +
						'Learn more</learnMoreLink>',
					'woocommerce-payments'
				),
				{
					learnMoreLink: (
						<ExternalLink href="https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/#store-settings" />
					),
				}
			) }
		</p>
	</>
);

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

	const [ isPreviewModalOpen, setPreviewModalOpen ] = useState( false );

	const [ isDirty, setIsDirty ] = useState( false );

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
		setIsDirty( true );
	};

	const handleIsStorefrontSwitcherEnabledClick = ( value ) => {
		setIsStorefrontSwitcherEnabledValue( value );
		setIsDirty( true );
	};

	const saveSettings = () => {
		setIsSavingSettings( true );
		submitStoreSettingsUpdate(
			isAutomaticSwitchEnabledValue,
			isStorefrontSwitcherEnabledValue
		);
		setIsSavingSettings( false );
		setIsDirty( false );
	};

	return (
		<>
			<SettingsSection
				description={ StoreSettingsDescription }
				className="multi-currency-settings-store-settings-section"
			>
				<LoadableBlock isLoading={ isLoading } numLines={ 10 }>
					<Card className="multi-currency-settings__wrapper">
						<CardBody className="wcpay-card-body">
							<CheckboxControl
								checked={ isAutomaticSwitchEnabledValue }
								onChange={ handleIsAutomaticSwitchEnabledClick }
								data-testid={ 'enable_auto_currency' }
								label={ __(
									'Automatically switch customers to their local currency if it has been enabled',
									'woocommerce-payments'
								) }
								help={ createInterpolateElement(
									__(
										'Customers will be notified via store alert banner. ' +
											'<previewLink>Preview</previewLink>',
										'woocommerce-payments'
									),
									{
										previewLink: (
											<Button
												isLink
												onClick={ () => {
													setPreviewModalOpen( true );
												} }
											/>
										),
									}
								) }
								__nextHasNoMarginBottom
							/>
							{ storeSettings.site_theme === 'Storefront' ? (
								<CheckboxControl
									checked={ isStorefrontSwitcherEnabledValue }
									onChange={
										handleIsStorefrontSwitcherEnabledClick
									}
									data-testid={ 'enable_storefront_switcher' }
									label={ __(
										'Add a currency switcher to the Storefront theme on breadcrumb section.',
										'woocommerce-payments'
									) }
									help={ createInterpolateElement(
										sprintf(
											/* translators: %s: url to the widgets page */
											__(
												'A currency switcher is also available in your widgets. ' +
													'<linkToWidgets>Configure now</linkToWidgets>',
												'woocommerce-payments'
											),
											'widgets.php'
										),
										{
											linkToWidgets: (
												// eslint-disable-next-line jsx-a11y/anchor-has-content
												<a href="widgets.php" />
											),
										}
									) }
									__nextHasNoMarginBottom
								/>
							) : null }
						</CardBody>
						<PreviewModal
							isPreviewModalOpen={ isPreviewModalOpen }
							setPreviewModalOpen={ setPreviewModalOpen }
							isStorefrontSwitcherEnabledValue={ false }
							isAutomaticSwitchEnabledValue={ true }
						/>
					</Card>
				</LoadableBlock>
			</SettingsSection>
			<SettingsSection className="multi-currency-settings-save-settings-section">
				<Button
					isPrimary
					isBusy={ isSavingSettings }
					disabled={ isSavingSettings || ! isDirty }
					onClick={ saveSettings }
				>
					{ __( 'Save changes', 'woocommerce-payments' ) }
				</Button>
			</SettingsSection>
		</>
	);
};

export default StoreSettings;
