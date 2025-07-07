/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { useState } from '@wordpress/element';
import { Button } from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */
import {
	useCurrentProtectionLevel,
	useAdvancedFraudProtectionSettings,
	useSettings,
	useGetSettings,
} from 'wcpay/data';
import { FraudProtectionHelpText, BasicFraudProtectionModal } from '../index';
import { getAdminUrl } from 'wcpay/utils';
import { ProtectionLevel } from '../../advanced-settings/constants';
import InlineNotice from 'components/inline-notice';
import { recordEvent } from 'tracks';

const ProtectionLevels: React.FC = () => {
	const [ isBasicModalOpen, setBasicModalOpen ] = useState( false );

	const initialProtectionLevelRef = useRef< string | null >( null );
	const initialSettingsRef = useRef< Record< string, any > | null >( null );

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();

	const [
		advancedFraudProtectionSettings,
	] = useAdvancedFraudProtectionSettings();

	const { isDirty } = useSettings();
	const currentSettings = useGetSettings();

	useEffect( () => {
		if ( initialProtectionLevelRef.current === null ) {
			initialProtectionLevelRef.current = currentProtectionLevel;
		}

		if ( initialSettingsRef.current === null && currentSettings ) {
			// eslint-disable-next-line @typescript-eslint/ban-ts-comment
			// @ts-ignore
			initialSettingsRef.current = { ...currentSettings };
		}
	}, [ currentProtectionLevel, currentSettings ] );

	const isAdvancedSettingsConfigured =
		Array.isArray( advancedFraudProtectionSettings ) &&
		0 < advancedFraudProtectionSettings.length;

	const handleLevelChange = ( level: string ) => () => {
		recordEvent( 'wcpay_fraud_protection_risk_level_preset_enabled', {
			preset: level,
		} );
		updateProtectionLevel( level );
	};

	const handleBasicModalOpen = () => {
		recordEvent( 'wcpay_fraud_protection_basic_modal_viewed' );
		setBasicModalOpen( true );
	};

	// Check if only the protection level setting has changed
	const isOnlyProtectionLevelChanged = (): boolean => {
		if ( ! initialSettingsRef.current || ! currentSettings ) {
			return false;
		}

		const allKeys = new Set( [
			...Object.keys( initialSettingsRef.current ),
			...Object.keys( currentSettings ),
		] );

		// Check each key to see if anything other than protection level changed
		for ( const key of allKeys ) {
			if ( key === 'current_protection_level' ) {
				continue;
			}

			const initialValue =
				initialSettingsRef.current[ key ] !== undefined
					? initialSettingsRef.current[ key ]
					: null;
			const currentValue =
				currentSettings[ key ] !== undefined
					? currentSettings[ key ]
					: null;

			// If values are different for any key other than protection level, more than one setting changed
			if (
				JSON.stringify( initialValue ) !==
				JSON.stringify( currentValue )
			) {
				return false;
			}
		}

		// If we got here, only the protection level changed
		return true;
	};

	const handleConfigureClick = () => {
		// Only clear the beforeunload handler if:
		// 1. The page has unsaved changes (isDirty is true)
		// 2. The initial protection level was Basic
		// 3. The current protection level is Advanced
		// 4. It's the only setting that changed on the page
		if (
			isDirty &&
			initialProtectionLevelRef.current === ProtectionLevel.BASIC &&
			currentProtectionLevel === ProtectionLevel.ADVANCED &&
			isOnlyProtectionLevelChanged()
		) {
			// When the only change is from Basic to Advanced, prevent the dialog
			window.onbeforeunload = null;
		}
	};

	return (
		<>
			{ 'error' === advancedFraudProtectionSettings && (
				<InlineNotice
					icon
					status="error"
					isDismissible={ false }
					className={ '' }
				>
					{ __(
						'There was an error retrieving your fraud protection settings. Please refresh the page to try again.',
						'woocommerce-payments'
					) }
				</InlineNotice>
			) }
			<fieldset
				disabled={ 'error' === advancedFraudProtectionSettings }
				id="fraud-protection-card-options"
			>
				<ul>
					<li>
						<div className="fraud-protection-radio-wrapper">
							<input
								name="fraud-protection-level-select"
								id="fraud-protection__basic-level"
								value={ ProtectionLevel.BASIC }
								type="radio"
								className="fraud-protection-radio-wrapper__item"
								checked={
									ProtectionLevel.BASIC ===
									currentProtectionLevel
								}
								onChange={ handleLevelChange(
									ProtectionLevel.BASIC
								) }
							/>
							<label
								className="fraud-protection-radio-wrapper__item"
								htmlFor="fraud-protection__basic-level"
							>
								{ __( 'Basic', 'woocommerce-payments' ) }
							</label>
							<HelpOutlineIcon
								size={ 18 }
								title={ __(
									'Basic level help icon',
									'woocommerce-payments'
								) }
								className="fraud-protection__help-icon"
								onClick={ handleBasicModalOpen }
							/>
							<BasicFraudProtectionModal
								level={ ProtectionLevel.BASIC }
								isBasicModalOpen={ isBasicModalOpen }
								setBasicModalOpen={ setBasicModalOpen }
							/>
						</div>
						<FraudProtectionHelpText
							level={ ProtectionLevel.BASIC }
						/>
					</li>
					<hr className="fraud-protection__list-divider" />
					<li className="fraud-protection__advanced-level-container">
						<label htmlFor="fraud-protection-level-select_advanced-level">
							<div className="fraud-protection-radio-wrapper">
								<input
									name="fraud-protection-level-select"
									id="fraud-protection-level-select_advanced-level"
									value={ ProtectionLevel.ADVANCED }
									type="radio"
									checked={
										ProtectionLevel.ADVANCED ===
										currentProtectionLevel
									}
									onChange={ handleLevelChange(
										ProtectionLevel.ADVANCED
									) }
								/>
								<p className="fraud-protection-radio-wrapper__item">
									{ __( 'Advanced', 'woocommerce-payments' ) }
								</p>
							</div>
							<FraudProtectionHelpText
								level={ ProtectionLevel.ADVANCED }
							/>
						</label>
						<Button
							href={ getAdminUrl( {
								page: 'wc-admin',
								path: '/payments/fraud-protection',
							} ) }
							isSecondary
							onClick={ handleConfigureClick }
							disabled={
								ProtectionLevel.ADVANCED !==
								currentProtectionLevel
							}
						>
							{ isAdvancedSettingsConfigured
								? __( 'Edit', 'woocommerce-payments' )
								: __( 'Configure', 'woocommerce-payments' ) }
						</Button>
					</li>
				</ul>
			</fieldset>
		</>
	);
};

export default ProtectionLevels;
