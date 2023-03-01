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
					<label htmlFor="fraud-protection__standard-level">
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={ 'fraud-protection__standard-level' }
								value={ 'standard' }
								type={ 'radio' }
								checked={
									'standard' === currentProtectionLevel
								}
								onChange={ () =>
									handleLevelChange( 'standard' )
								}
							/>
							<p className="fraud-protection-single-radio-wrapper__item">
								{ __( 'Standard', 'woocommerce-payments' ) }
							</p>
							<p
								className={
									'fraud-protection-single-radio-wrapper__item--recommended'
								}
							>
								{ __(
									'(Recommended)',
									'woocommerce-payments'
								) }
							</p>
							<HelpOutlineIcon
								size={ 18 }
								title="Standard level help icon"
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
					</label>
				</li>
				<li>
					<label htmlFor="fraud-protection__high-level">
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={ 'fraud-protection__high-level' }
								value={ 'high' }
								type={ 'radio' }
								checked={ 'high' === currentProtectionLevel }
								onChange={ () => handleLevelChange( 'high' ) }
							/>
							<p className="fraud-protection-single-radio-wrapper__item">
								{ __( 'High', 'woocommerce-payments' ) }
							</p>
							<HelpOutlineIcon
								size={ 18 }
								title="High level help icon"
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
					</label>
				</li>
			</ul>
		</fieldset>
	);
};

export default ProtectionLevels;
