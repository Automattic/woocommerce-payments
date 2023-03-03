/**
 * External dependencies
 */
import React, { useEffect, useLayoutEffect, useState } from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { LoadableBlock } from 'wcpay/components/loadable';

/**
 * Internal dependencies
 */
import './../style.scss';
import SettingsLayout from 'wcpay/settings/settings-layout';
import AVSMismatchRuleCard from './cards/avs-mismatch';
import CVCVerificationRuleCard from './cards/cvc-verification';
import InternationalIPAddressRuleCard from './cards/international-ip-address';
import InternationalBillingAddressRuleCard from './cards/international-billing-address';
import AddressMismatchRuleCard from './cards/address-mismatch';
import OrderVelocityRuleCard from './cards/order-velocity';
import PurchasePriceThresholdRuleCard from './cards/purchase-price-threshold';
import OrderItemsThresholdRuleCard from './cards/order-items-threshold';
import FraudPreventionSettingsContext from './context';
import { useSettings } from '../../../data';
import { Button } from '@wordpress/components';

const Breadcrumb = () => (
	<h2 className="fraud-protection-header-breadcrumb">
		<Link href="">
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
	const [ isSavingSettings, setIsSavingSettings ] = useState( false );
	const [
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	] = useState( {} );

	useEffect( () => {
		setAdvancedFraudProtectionSettings(
			settings.advanced_fraud_protection_settings
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

	const handleSaveSettings = async () => {
		setIsSavingSettings( true );
		await saveSettings( settings );
		setIsSavingSettings( false );
	};

	const contextValue = {
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	};

	return (
		<FraudPreventionSettingsContext.Provider value={ contextValue }>
			<SettingsLayout displayBanner={ false }>
				<div className="fraud-protection-advanced-settings-layout">
					<Breadcrumb />
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
			</SettingsLayout>
			<SaveFraudProtectionSettingsButton>
				<div className="fraud-protection-header-save-button">
					<Button
						isPrimary
						isBusy={ isSavingSettings }
						onClick={ handleSaveSettings }
						disabled={ isSavingSettings }
					>
						{ __( 'Save Changes', 'woocommerce-payments' ) }
					</Button>
				</div>
			</SaveFraudProtectionSettingsButton>
		</FraudPreventionSettingsContext.Provider>
	);
};

export default FraudProtectionAdvancedSettingsPage;
