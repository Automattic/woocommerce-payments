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
import { useCurrentProtectionLevel, useCurrencies } from 'wcpay/data';
import {
	FraudProtectionHelpText,
	HighFraudProtectionModal,
	StandardFraudProtectionModal,
} from '../index';
import interpolateComponents from 'interpolate-components';

const ProtectionLevels = () => {
	const [ isStandardModalOpen, setStandardModalOpen ] = useState( false );
	const [ isHighModalOpen, setHighModalOpen ] = useState( false );
	const { currencies } = useCurrencies();
	const storeCurrency = currencies.default ? currencies.default : {};

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();

	const handleLevelChange = ( level ) => {
		updateProtectionLevel( level );
	};

	return (
		<fieldset>
			<ul>
				<li>
					<div className="fraud-protection-single-radio-wrapper">
						<input
							name={ 'fraud-protection-level-select' }
							id={ 'fraud-protection__standard-level' }
							value={ 'standard' }
							type={ 'radio' }
							className={
								'fraud-protection-single-radio-wrapper__item'
							}
							checked={ 'standard' === currentProtectionLevel }
							onChange={ () => handleLevelChange( 'standard' ) }
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
							level="standard"
							isStandardModalOpen={ isStandardModalOpen }
							setStandardModalOpen={ setStandardModalOpen }
							storeCurrency={ storeCurrency }
						/>
					</div>
					<FraudProtectionHelpText level="standard" />
				</li>
				<li>
					<div className="fraud-protection-single-radio-wrapper">
						<input
							name={ 'fraud-protection-level-select' }
							id={ 'fraud-protection__high-level' }
							value={ 'high' }
							type={ 'radio' }
							className={
								'fraud-protection-single-radio-wrapper__item'
							}
							checked={ 'high' === currentProtectionLevel }
							onChange={ () => handleLevelChange( 'high' ) }
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
							level="high"
							isHighModalOpen={ isHighModalOpen }
							setHighModalOpen={ setHighModalOpen }
							storeCurrency={ storeCurrency }
						/>
					</div>
					<FraudProtectionHelpText level="high" />
				</li>
			</ul>
		</fieldset>
	);
};

export default ProtectionLevels;
