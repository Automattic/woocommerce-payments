/**
 * External dependencies
 */
import React, { useEffect, useLayoutEffect, useState, useRef } from 'react';
import ReactDOM from 'react-dom';
import { sprintf, __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { LoadableBlock } from 'wcpay/components/loadable';
import { Button, Notice } from '@wordpress/components';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	useCurrentProtectionLevel,
	useAdvancedFraudProtectionSettings,
	useSettings,
} from '../../../data';
import ErrorBoundary from '../../../components/error-boundary';
import { getAdminUrl } from '../../../utils';
import SettingsLayout from 'wcpay/settings/settings-layout';
import AVSMismatchRuleCard from './cards/avs-mismatch';
import CVCVerificationRuleCard from './cards/cvc-verification';
import InternationalIPAddressRuleCard from './cards/international-ip-address';
import InternationalBillingAddressRuleCard from './cards/international-billing-address';
import AddressMismatchRuleCard from './cards/address-mismatch';
import PurchasePriceThresholdRuleCard, {
	PurchasePriceThresholdValidation,
} from './cards/purchase-price-threshold';
import OrderItemsThresholdRuleCard, {
	OrderItemsThresholdValidation,
} from './cards/order-items-threshold';
import FraudPreventionSettingsContext from './context';
import './../style.scss';

import { ProtectionLevel } from './constants';
import { readRuleset, writeRuleset } from './utils';
import wcpayTracks from 'tracks';

const observerMapping = {
	'avs-mismatch-card': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_avs_mismatch_viewed',
	},
	'cvc-verification-card': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_cvc_verification_viewed',
	},
	'international-ip-address-card': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_international_ip_address_card_viewed',
	},
	'international-billing-address': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_international_billing_address_viewed',
	},
	'address-mismatch-card': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_address_mismatch_viewed',
	},
	'purchase-price-threshold-card': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_price_threshold_viewed',
	},
	'order-items-threshold-card': {
		event:
			'wcpay_fraud_protection_advanced_settings_card_items_threshold_viewed',
	},
};

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
	const { saveSettings, isLoading, isSaving } = useSettings();

	const cardObserver = useRef( null );

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();
	const [
		advancedFraudProtectionSettings,
		updateAdvancedFraudProtectionSettings,
	] = useAdvancedFraudProtectionSettings();
	const [ validationError, setValidationError ] = useState( null );
	const [ protectionSettingsUI, setProtectionSettingsUI ] = useState( {} );
	const [
		protectionSettingsChanged,
		setProtectionSettingsChanged,
	] = useState( false );

	useEffect( () => {
		setProtectionSettingsUI(
			readRuleset( advancedFraudProtectionSettings )
		);
	}, [ advancedFraudProtectionSettings ] );

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
		const validators = {
			order_items_threshold: OrderItemsThresholdValidation,
			purchase_price_threshold: PurchasePriceThresholdValidation,
		};

		return Object.keys( validators )
			.map( ( key ) =>
				validators[ key ](
					fraudProtectionSettings[ key ],
					setValidationError
				)
			)
			.every( Boolean );
	};

	const handleSaveSettings = () => {
		if ( validateSettings( protectionSettingsUI ) ) {
			if ( ProtectionLevel.ADVANCED !== currentProtectionLevel ) {
				updateProtectionLevel( ProtectionLevel.ADVANCED );
				dispatch( 'core/notices' ).createSuccessNotice(
					__(
						'Current protection level is set to "advanced".',
						'woocommerce-payments'
					)
				);
			}

			const settings = writeRuleset( protectionSettingsUI );

			updateAdvancedFraudProtectionSettings( settings );

			saveSettings();

			wcpayTracks.recordEvent(
				'wcpay_fraud_protection_advanced_settings_saved',
				{ settings: JSON.stringify( settings ) }
			);
		} else {
			window.scrollTo( {
				top: 0,
			} );
		}
	};

	// Hack to make "WooCommerce > Settings" the active selected menu item.
	useEffect( () => {
		const wcSettingsMenuItem = document.querySelector(
			'#toplevel_page_woocommerce a[href="admin.php?page=wc-settings"]'
		);
		if ( wcSettingsMenuItem ) {
			wcSettingsMenuItem.setAttribute( 'aria-current', 'page' );
			wcSettingsMenuItem.classList.add( 'current' );
			wcSettingsMenuItem.parentElement.classList.add( 'current' );
		}
	}, [] );

	// Intersection observer callback for tracking card viewed events.
	const observerCallback = ( entries ) => {
		entries.forEach( ( entry ) => {
			const { target, intersectionRatio } = entry;

			if ( 0 < intersectionRatio ) {
				// element is at least partially visible.
				const { id } = target;
				const { event } = observerMapping[ id ] || {};

				if ( event ) {
					wcpayTracks.recordEvent( event );
				}

				cardObserver.current?.unobserve(
					document.getElementById( target.id )
				);
			}
		} );
	};

	useEffect( () => {
		if ( isLoading ) return;

		cardObserver.current = new IntersectionObserver( observerCallback );

		Object.keys( observerMapping ).forEach( ( selector ) => {
			const element = document.getElementById( selector );

			if ( element ) {
				cardObserver.current?.observe( element );
			}
		} );

		return () => {
			cardObserver.current?.disconnect();
		};
	}, [ isLoading ] );

	return (
		<FraudPreventionSettingsContext.Provider
			value={ {
				protectionSettingsUI,
				setProtectionSettingsUI,
				protectionSettingsChanged,
				setProtectionSettingsChanged,
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
						{ 'error' === advancedFraudProtectionSettings && (
							<div className="fraud-protection-advanced-settings-error-notice">
								<Notice status="error" isDismissible={ false }>
									{ __(
										'There was an error retrieving your fraud protection settings.' +
											' Please refresh the page to try again.',
										'woocommerce-payments'
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
						isBusy={ isSaving }
						onClick={ handleSaveSettings }
						disabled={
							isSaving ||
							isLoading ||
							'error' === advancedFraudProtectionSettings
						}
					>
						{ __( 'Save Changes', 'woocommerce-payments' ) }
					</Button>
				</div>
			</SaveFraudProtectionSettingsButton>
		</FraudPreventionSettingsContext.Provider>
	);
};

export default FraudProtectionAdvancedSettingsPage;
