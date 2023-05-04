/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	useCurrentProtectionLevel,
	useAdvancedFraudProtectionSettings,
} from 'wcpay/data';
import { FraudProtectionHelpText, BasicFraudProtectionModal } from '../index';
import { getAdminUrl } from 'wcpay/utils';
import { ProtectionLevel } from '../../advanced-settings/constants';
import InlineNotice from '../../../../components/inline-notice';
import wcpayTracks from 'tracks';

const ProtectionLevels = () => {
	const [ isBasicModalOpen, setBasicModalOpen ] = useState( false );

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();

	const [
		advancedFraudProtectionSettings,
	] = useAdvancedFraudProtectionSettings();

	const handleLevelChange = ( level ) => () => {
		wcpayTracks.recordEvent(
			'wcpay_fraud_protection_risk_level_preset_enabled',
			{ preset: level }
		);
		updateProtectionLevel( level );
	};

	const handleBasicModalOpen = () => {
		wcpayTracks.recordEvent( 'wcpay_fraud_protection_basic_modal_viewed' );
		setBasicModalOpen( true );
	};

	return (
		<>
			{ 'error' === advancedFraudProtectionSettings && (
				<InlineNotice status="error" isDismissible={ false }>
					{ __(
						'There was an error retrieving your fraud protection settings. Please refresh the page to try again.',
						'woocommerce-payments'
					) }
				</InlineNotice>
			) }
			<fieldset disabled={ 'error' === advancedFraudProtectionSettings }>
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
								title="Basic level help icon"
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
							disabled={
								ProtectionLevel.ADVANCED !==
								currentProtectionLevel
							}
						>
							{ __( 'Edit', 'woocommerce-payments' ) }
						</Button>
					</li>
				</ul>
			</fieldset>
		</>
	);
};

export default ProtectionLevels;
