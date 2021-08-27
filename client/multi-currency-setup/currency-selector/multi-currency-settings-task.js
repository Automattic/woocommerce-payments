/**
 * External dependencies
 */
import React, { useCallback, useContext, useState, useEffect } from 'react';
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import './multi-currency-settings-task.scss';

import { useStoreSettings } from 'wcpay/data';

const MultiCurrencySettingsTask = () => {
	const { storeSettings, submitStoreSettingsUpdate } = useStoreSettings();

	const [ status, setStatus ] = useState( 'resolved' );

	const [
		isAutomaticSwitchEnabledValue,
		setIsAutomaticSwitchEnabledValue,
	] = useState( false );

	const [
		isStorefrontSwitcherEnabledValue,
		setIsStorefrontSwitcherEnabledValue,
	] = useState( false );

	const [ isPreviewModalOpen, setPreviewModalOpen ] = useState( false );

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

	const { setCompleted } = useContext( WizardTaskContext );

	const handlePreviewModalOpenClick = useCallback( () => {
		setPreviewModalOpen( true );
	}, [ setPreviewModalOpen ] );

	const handlePreviewModalCloseClick = useCallback( () => {
		setPreviewModalOpen( false );
	}, [ setPreviewModalOpen ] );

	const handleIsAutomaticSwitchEnabledClick = useCallback(
		( value ) => {
			setIsAutomaticSwitchEnabledValue( value );
		},
		[ setIsAutomaticSwitchEnabledValue ]
	);

	const handleIsStorefrontSwitcherEnabledClick = useCallback(
		( value ) => {
			setIsStorefrontSwitcherEnabledValue( value );
		},
		[ setIsStorefrontSwitcherEnabledValue ]
	);

	const handleContinueClick = useCallback( () => {
		setStatus( 'pending' );
		submitStoreSettingsUpdate(
			isAutomaticSwitchEnabledValue,
			isStorefrontSwitcherEnabledValue
		);
		setStatus( 'resolved' );
		setCompleted( true, 'setup-complete' );
	}, [
		setCompleted,
		isAutomaticSwitchEnabledValue,
		isStorefrontSwitcherEnabledValue,
		setStatus,
		submitStoreSettingsUpdate,
	] );

	return (
		<WizardTaskItem
			title={ interpolateComponents( {
				mixedString: __(
					'{{wrapper}}Review store settings{{/wrapper}}',
					'woocommerce-payments'
				),
				components: {
					wrapper: <span />,
				},
			} ) }
			visibleDescription={ __(
				'These settings can be changed any time by visiting the multi-currency settings',
				'woocommerce-payments'
			) }
			index={ 2 }
		>
			<CollapsibleBody className="multi-currency-settings-task__body">
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ __(
						'These settings can be changed any time by visiting the multi-currency settings',
						'woocommerce-payments'
					) }
				</p>
				<Card className="multi-currency-settings-task__wrapper">
					<CardBody>
						<CheckboxControl
							checked={ isAutomaticSwitchEnabledValue }
							onChange={ handleIsAutomaticSwitchEnabledClick }
							label={ __(
								'Automatically switch customers to their local currency if it has been enabled',
								'woocommerce-payments'
							) }
						/>
						<div className="multi-currency-settings-task__description">
							Customers will be notified via store alert banner.
						</div>
						<CheckboxControl
							checked={ isStorefrontSwitcherEnabledValue }
							onChange={ handleIsStorefrontSwitcherEnabledClick }
							label={ __(
								'Add a currency switcher to the cart widget',
								'woocommerce-payments'
							) }
						/>
					</CardBody>
				</Card>
				<Button
					isBusy={ 'pending' === status }
					disabled={ 'pending' === status }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Continue', 'woocommerce-payments' ) }
				</Button>
				<Button
					isBusy={ 'pending' === status }
					disabled={ 'pending' === status }
					onClick={ handlePreviewModalOpenClick }
					className={ 'multi-currency-setup-preview-button' }
					isTertiary
				>
					{ __( 'Preview', 'woocommerce-payments' ) }
				</Button>
				{ isPreviewModalOpen && (
					<Modal
						title={ __( 'Preview', 'woocommerce-payments' ) }
						isDismissible={ true }
						className="multi-currency-setup-preview-modal"
						shouldCloseOnClickOutside={ false }
						onRequestClose={ handlePreviewModalCloseClick }
					>
						<iframe
							title={ __( 'Preview', 'woocommerce-payments' ) }
							className={ 'multi-currency-setup-preview-iframe' }
							src={
								'/?is_mc_onboarding_simulation=1&enable_storefront_switcher=' +
								isStorefrontSwitcherEnabledValue +
								'&enable_auto_currency=' +
								isAutomaticSwitchEnabledValue
							}
						></iframe>
					</Modal>
				) }
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default MultiCurrencySettingsTask;
