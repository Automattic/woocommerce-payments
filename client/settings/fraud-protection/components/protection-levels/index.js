/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	useCurrentProtectionLevel,
	useCurrencies,
	useAdvancedFraudProtectionSettings,
} from 'wcpay/data';
import {
	FraudProtectionHelpText,
	HighFraudProtectionModal,
	StandardFraudProtectionModal,
} from '../index';
import interpolateComponents from '@automattic/interpolate-components';
import { Button } from '@wordpress/components';
import { getAdminUrl } from 'wcpay/utils';
import { ProtectionLevel } from '../../advanced-settings/constants';
import InlineNotice from '../../../../components/inline-notice';
import wcpayTracks from 'tracks';

const ProtectionLevels = () => {
	const [ isStandardModalOpen, setStandardModalOpen ] = useState( false );
	const [ isHighModalOpen, setHighModalOpen ] = useState( false );
	const { currencies } = useCurrencies();
	const storeCurrency = currencies.default ? currencies.default : {};

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();

	const [
		advancedFraudProtectionSettings,
	] = useAdvancedFraudProtectionSettings();

	const handleLevelChange = ( level ) => {
		wcpayTracks.recordEvent(
			'wcpay_fraud_protection_risk_level_preset_enabled',
			{ preset: level }
		);
		updateProtectionLevel( level );
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
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={ 'fraud-protection__basic-level' }
								value={ ProtectionLevel.BASIC }
								type={ 'radio' }
								className={
									'fraud-protection-single-radio-wrapper__item'
								}
								checked={
									ProtectionLevel.BASIC ===
									currentProtectionLevel
								}
								onChange={ () =>
									handleLevelChange( ProtectionLevel.BASIC )
								}
							/>
							<label
								className="fraud-protection-single-radio-wrapper__item"
								htmlFor="fraud-protection__basic-level"
							>
								{ __( 'Basic', 'woocommerce-payments' ) }
							</label>
						</div>
						<FraudProtectionHelpText
							level={ ProtectionLevel.BASIC }
						/>
					</li>
					<li>
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={ 'fraud-protection__standard-level' }
								value={ ProtectionLevel.STANDARD }
								type={ 'radio' }
								className={
									'fraud-protection-single-radio-wrapper__item'
								}
								checked={
									ProtectionLevel.STANDARD ===
									currentProtectionLevel
								}
								onChange={ () =>
									handleLevelChange(
										ProtectionLevel.STANDARD
									)
								}
							/>
							<label
								className="fraud-protection-single-radio-wrapper__item"
								htmlFor="fraud-protection__standard-level"
							>
								{ interpolateComponents( {
									mixedString: __(
										'Standard {{recommended}}(Recommended){{/recommended}}',
										'woocommerce-payments'
									),
									components: {
										recommended: (
											<span className="fraud-protection-single-radio-wrapper__item--recommended" />
										),
									},
								} ) }
							</label>
							<HelpOutlineIcon
								size={ 18 }
								title="Standard level help icon"
								className="fraud-protection__help-icon"
								onClick={ () => setStandardModalOpen( true ) }
							/>
							<StandardFraudProtectionModal
								level={ ProtectionLevel.STANDARD }
								isStandardModalOpen={ isStandardModalOpen }
								setStandardModalOpen={ setStandardModalOpen }
								storeCurrency={ storeCurrency }
							/>
						</div>
						<FraudProtectionHelpText
							level={ ProtectionLevel.STANDARD }
						/>
					</li>
					<li>
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={ 'fraud-protection__high-level' }
								value={ ProtectionLevel.HIGH }
								type={ 'radio' }
								className={
									'fraud-protection-single-radio-wrapper__item'
								}
								checked={
									ProtectionLevel.HIGH ===
									currentProtectionLevel
								}
								onChange={ () =>
									handleLevelChange( ProtectionLevel.HIGH )
								}
							/>
							<label
								className="fraud-protection-single-radio-wrapper__item"
								htmlFor="fraud-protection__high-level"
							>
								{ __( 'High', 'woocommerce-payments' ) }
							</label>
							<HelpOutlineIcon
								size={ 18 }
								title="High level help icon"
								className="fraud-protection__help-icon"
								onClick={ () => setHighModalOpen( true ) }
							/>
							<HighFraudProtectionModal
								level={ ProtectionLevel.HIGH }
								isHighModalOpen={ isHighModalOpen }
								setHighModalOpen={ setHighModalOpen }
								storeCurrency={ storeCurrency }
							/>
						</div>
						<FraudProtectionHelpText
							level={ ProtectionLevel.HIGH }
						/>
					</li>
					<hr className="fraud-protection__list-divider" />
					<li className="fraud-protection__advanced-level-container">
						<label htmlFor="fraud-protection-level-select_advanced-level">
							<div className="fraud-protection-single-radio-wrapper">
								<input
									name={ 'fraud-protection-level-select' }
									id={
										'fraud-protection-level-select_advanced-level'
									}
									value={ ProtectionLevel.ADVANCED }
									type={ 'radio' }
									checked={
										ProtectionLevel.ADVANCED ===
										currentProtectionLevel
									}
									onChange={ () =>
										handleLevelChange(
											ProtectionLevel.ADVANCED
										)
									}
								/>
								<p className="fraud-protection-single-radio-wrapper__item">
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
							{ __( 'Configure', 'woocommerce-payments' ) }
						</Button>
					</li>
				</ul>
			</fieldset>
		</>
	);
};

export default ProtectionLevels;
