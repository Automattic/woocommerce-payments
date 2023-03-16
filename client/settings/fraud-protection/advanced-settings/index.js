/**
 * External dependencies
 */
import React, { useEffect, useLayoutEffect, useState } from 'react';
import ReactDOM from 'react-dom';
import { sprintf, __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { LoadableBlock } from 'wcpay/components/loadable';
import { Button, Notice } from '@wordpress/components';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useCurrentProtectionLevel, useSettings } from '../../../data';
import ErrorBoundary from '../../../components/error-boundary';
import { getAdminUrl } from '../../../utils';
import SettingsLayout from 'wcpay/settings/settings-layout';
import AVSMismatchRuleCard from './cards/avs-mismatch';
import CVCVerificationRuleCard from './cards/cvc-verification';
import InternationalIPAddressRuleCard from './cards/international-ip-address';
import InternationalBillingAddressRuleCard from './cards/international-billing-address';
import AddressMismatchRuleCard from './cards/address-mismatch';
import OrderVelocityRuleCard, {
	OrderVelocityValidation,
} from './cards/order-velocity';
import PurchasePriceThresholdRuleCard, {
	PurchasePriceThresholdValidation,
} from './cards/purchase-price-threshold';
import OrderItemsThresholdRuleCard, {
	OrderItemsThresholdValidation,
} from './cards/order-items-threshold';
import FraudPreventionSettingsContext from './context';
import './../style.scss';

import { ProtectionLevel, readRuleset, writeRuleset } from './constants';

const Breadcrumb = () => (
	<h2 className="fraud-protection-header-breadcrumb">
		<Link
			type="wp-admin"
			href={ getAdminUrl( {
				page: 'wc-settings',
				tab: 'checkout',
				section: 'woocommerce_payments',
			} ) }
		>
			{ __( 'WooCommerce Payments', 'woocommerce-payments' ) }
		</Link>
		&nbsp;&gt;&nbsp;
		{ __( 'Advanced fraud protection', 'woocommerce-payments' ) }
	</h2>
);

const SaveFraudProtectionSettingsButton = ( { children } ) => {
	const headerElement = document.querySelector(
		'.woocommerce-layout__header-wrapper'
	);
	return headerElement && ReactDOM.createPortal( children, headerElement );
};

const FraudProtectionAdvancedSettingsPage = () => {
	const { settings, saveSettings, isLoading } = useSettings();
	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();
	const [ isSavingSettings, setIsSavingSettings ] = useState( false );
	const [ validationError, setValidationError ] = useState( null );
	const [
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	] = useState( {} );

	useEffect( () => {
		setAdvancedFraudProtectionSettings(
			readRuleset( settings.advanced_fraud_protection_settings )
		);
	}, [ settings ] );

	useLayoutEffect( () => {
		const saveButton = document.querySelector(
			'.fraud-protection-header-save-button'
		);
		if ( saveButton ) {
			document
				.querySelector( '.woocommerce-layout__header-heading' )
				.after( saveButton );
		}
	} );

	const validateSettings = ( fraudProtectionSettings ) => {
		setValidationError( null );
		let validationResult = true;
		validationResult &&= OrderItemsThresholdValidation(
			fraudProtectionSettings,
			setValidationError
		);
		validationResult &&= OrderVelocityValidation(
			fraudProtectionSettings,
			setValidationError
		);
		validationResult &&= PurchasePriceThresholdValidation(
			fraudProtectionSettings,
			setValidationError
		);
		return validationResult;
	};

	const handleSaveSettings = async () => {
		if ( validateSettings( advancedFraudProtectionSettings ) ) {
			setIsSavingSettings( true );
			if ( ProtectionLevel.ADVANCED !== currentProtectionLevel ) {
				await updateProtectionLevel( ProtectionLevel.ADVANCED );
				dispatch( 'core/notices' ).createSuccessNotice(
					__(
						'Current protection level is set to "advanced".',
						'woocommerce-payments'
					)
				);
			}
			settings.advanced_fraud_protection_settings = writeRuleset(
				advancedFraudProtectionSettings
			);
			await saveSettings( settings );
			setIsSavingSettings( false );
		} else {
			window.scrollTo( {
				top: 0,
			} );
		}
	};

	// Hack to make "WooCommerce > Settings" the active selected menu item.
	const wcSettingsMenuItem = document.querySelector(
		'#toplevel_page_woocommerce a[href="admin.php?page=wc-settings"]'
	);
	if ( wcSettingsMenuItem ) {
		wcSettingsMenuItem.setAttribute( 'aria-current', 'page' );
		wcSettingsMenuItem.classList.add( 'current' );
		wcSettingsMenuItem.parentElement.classList.add( 'current' );
	}

	return (
		<FraudPreventionSettingsContext.Provider
			value={ {
				advancedFraudProtectionSettings,
				setAdvancedFraudProtectionSettings,
			} }
		>
			<SettingsLayout displayBanner={ false }>
				<ErrorBoundary>
					<div className="fraud-protection-advanced-settings-layout">
						<Breadcrumb />
						{ validationError && (
							<div className="fraud-protection-advanced-settings-error-notice">
								<Notice
									status="error"
									isDismissible={ true }
									onRemove={ () => {
										setValidationError( null );
									} }
								>
									{ sprintf(
										'%s %s',
										__(
											'Settings were not saved.',
											'woocommerce-payments'
										),
										validationError
									) }
								</Notice>
							</div>
						) }
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<AVSMismatchRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<CVCVerificationRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<InternationalIPAddressRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<InternationalBillingAddressRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<AddressMismatchRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<OrderVelocityRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<PurchasePriceThresholdRuleCard />
						</LoadableBlock>
						<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
							<OrderItemsThresholdRuleCard />
						</LoadableBlock>
					</div>
				</ErrorBoundary>
			</SettingsLayout>
			<SaveFraudProtectionSettingsButton>
				<div className="fraud-protection-header-save-button">
					<Button
						isPrimary
						isBusy={ isSavingSettings }
						onClick={ handleSaveSettings }
						disabled={ isSavingSettings || isLoading }
					>
						{ __( 'Save Changes', 'woocommerce-payments' ) }
					</Button>
				</div>
			</SaveFraudProtectionSettingsButton>
		</FraudPreventionSettingsContext.Provider>
	);
};

export default FraudProtectionAdvancedSettingsPage;
