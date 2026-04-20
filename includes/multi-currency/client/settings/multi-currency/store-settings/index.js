/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	ExternalLink,
	RadioControl,
} from '@wordpress/components';
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
						// @ts-expect-error: children is provided when interpolating the component
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
		isDirty,
		isSaving,
		updateStoreSettingValues,
		saveStoreSettings,
	} = useStoreSettings();

	useEffect( () => {
		if ( ! isDirty ) {
			window.onbeforeunload = null;
		}
	}, [ isDirty ] );

	const [ isPreviewModalOpen, setPreviewModalOpen ] = useState( false );

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
								checked={
									!! storeSettings.enable_auto_currency
								}
								onChange={ ( value ) =>
									updateStoreSettingValues( {
										enable_auto_currency: value,
									} )
								}
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
												__next40pxDefaultSize
											/>
										),
									}
								) }
								__nextHasNoMarginBottom
							/>
							{ storeSettings.is_cache_optimized_feature_enabled ? (
								<RadioControl
									label={ __(
										'Price rendering mode',
										'woocommerce-payments'
									) }
									help={ __(
										'Choose how multi-currency prices are rendered. "Optimized for caching" outputs identical HTML for all visitors and converts prices client-side, allowing hosting providers to cache pages effectively.',
										'woocommerce-payments'
									) }
									selected={
										storeSettings.rendering_mode || 'speed'
									}
									options={ [
										{
											label: __(
												'Optimized for speed (default)',
												'woocommerce-payments'
											),
											value: 'speed',
										},
										{
											label: __(
												'Optimized for caching',
												'woocommerce-payments'
											),
											value: 'cache',
										},
									] }
									onChange={ ( value ) =>
										updateStoreSettingValues( {
											rendering_mode: value,
										} )
									}
								/>
							) : null }
							{ storeSettings.site_theme === 'Storefront' ? (
								<CheckboxControl
									checked={
										!! storeSettings.enable_storefront_switcher
									}
									onChange={ ( value ) =>
										updateStoreSettingValues( {
											enable_storefront_switcher: value,
										} )
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
					isBusy={ isSaving }
					disabled={ isSaving || ! isDirty }
					onClick={ saveStoreSettings }
					__next40pxDefaultSize
				>
					{ __( 'Save changes', 'woocommerce-payments' ) }
				</Button>
			</SettingsSection>
		</>
	);
};

export default StoreSettings;
