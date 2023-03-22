/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, Icon, ToggleControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './../style.scss';
import Tooltip from '../../../components/tooltip';
import FraudPreventionSettingsContext from './context';

const FraudProtectionRuleToggle = ( {
	setting,
	label,
	helpText,
	children,
} ) => {
	const {
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	} = useContext( FraudPreventionSettingsContext );

	const [ toggleState, setToggleState ] = useState( false );
	const [ checkState, setCheckState ] = useState( false );

	// Set initial states from saved settings.
	useEffect( () => {
		if (
			advancedFraudProtectionSettings &&
			advancedFraudProtectionSettings[ setting ]
		) {
			setToggleState(
				advancedFraudProtectionSettings[ setting ].enabled
			);
			setCheckState( advancedFraudProtectionSettings[ setting ].block );
		}
	}, [
		advancedFraudProtectionSettings,
		setToggleState,
		setCheckState,
		setting,
	] );

	// Set global object values from input changes.
	useEffect( () => {
		if (
			advancedFraudProtectionSettings &&
			advancedFraudProtectionSettings[ setting ]
		) {
			advancedFraudProtectionSettings[ setting ].enabled = toggleState;
			advancedFraudProtectionSettings[ setting ].block = checkState;
			setAdvancedFraudProtectionSettings(
				advancedFraudProtectionSettings
			);
		}
	}, [
		setting,
		toggleState,
		checkState,
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	] );

	// Render view.
	return (
		advancedFraudProtectionSettings && (
			<div className="fraud-protection-rule-toggle">
				<strong>
					{ __( 'Enable filtering', 'woocommerce-payments' ) }
				</strong>
				<ToggleControl
					label={ label }
					key={ setting }
					help={ helpText }
					checked={ toggleState }
					className="fraud-protection-rule-toggle-toggle"
					onChange={ () => setToggleState( ( value ) => ! value ) }
				></ToggleControl>
				{ toggleState && (
					<div>
						{ children }
						<strong>
							{ __( 'Advanced', 'woocommerce-payments' ) }
						</strong>
						<div className="fraud-protection-rule-toggle-checkbox-container">
							<CheckboxControl
								label={ __(
									'Block Payment',
									'woocommerce-payments'
								) }
								className="fraud-protection-rule-toggle-checkbox"
								checked={ checkState }
								onChange={ () =>
									setCheckState( ( state ) => ! state )
								}
							></CheckboxControl>
							<Tooltip
								content={ __(
									'WooCommerce Payments will automatically cancel orders that match this filter.',
									'woocommerce-payments'
								) }
							>
								<div className="fraud-protection-rule-toggle-checkbox-container-help">
									<Icon
										icon={
											<svg
												width="16"
												height="16"
												viewBox="3 3 18 18"
												fill="none"
												xmlns="http://www.w3.org/2000/svg"
											>
												<path
													d={
														'M9.75 10.25C9.75 9.00736 10.7574 8 12 8C13.2426 8 14.25 9.00736 14.25 10.25C14.25 \
														11.4083 13.3748 12.3621 12.2496 12.4863C12.1124 12.5015 12 12.6119 12 12.75V14M12 \
														15V16.5M20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12C4 7.58172 \
														7.58172 4 12 4C16.4183 4 20 7.58172 20 12Z'
													}
													stroke="#1E1E1E"
													strokeWidth="1.5"
												/>
											</svg>
										}
									></Icon>
								</div>
							</Tooltip>
						</div>
					</div>
				) }
			</div>
		)
	);
};

export default FraudProtectionRuleToggle;
