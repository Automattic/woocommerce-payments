/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ToggleControl, RadioControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './../style.scss';
import FraudPreventionSettingsContext from './context';

interface FraudProtectionRuleToggleProps {
	setting: string;
	label: string;
}

const filterActions = {
	REVIEW: 'review',
	BLOCK: 'block',
};

const radioOptions = [
	{
		label: __( 'Authorize and hold for review', 'woocommerce-payments' ),
		value: filterActions.REVIEW,
	},
	{
		label: __( 'Block Payment', 'woocommerce-payments' ),
		value: filterActions.BLOCK,
	},
];

const helpTextMapping = {
	unchecked: __(
		'When enabled, the payment method will not be charged until you review and approve the transaction.'
	),
	[ filterActions.REVIEW ]: __(
		'The payment method will not be charged until you review and approve the transaction.'
	),
	[ filterActions.BLOCK ]: __( 'The payment will be blocked.' ),
};

const getHelpText = ( toggleState: boolean, filterAction: string ) => {
	if ( ! toggleState ) return helpTextMapping.unchecked;

	return helpTextMapping[ filterAction ];
};

const FraudProtectionRuleToggle: React.FC< FraudProtectionRuleToggleProps > = ( {
	setting,
	label,
	children,
} ) => {
	const {
		protectionSettingsUI,
		setProtectionSettingsUI,
		setProtectionSettingsChanged,
	} = useContext( FraudPreventionSettingsContext );

	const [ toggleState, setToggleState ] = useState( false );
	const [ filterAction, setFilterAction ] = useState( filterActions.REVIEW );

	const settingUI = protectionSettingsUI?.[ setting ];

	// Set initial states from saved settings.
	useEffect( () => {
		if ( ! settingUI ) return;

		setToggleState( settingUI.enabled );
		setFilterAction(
			settingUI.block ? filterActions.BLOCK : filterActions.REVIEW
		);
	}, [ settingUI ] );

	// Set global object values from input changes.
	useEffect( () => {
		if ( ! settingUI ) return;

		settingUI.enabled = toggleState;
		settingUI.block = filterActions.BLOCK === filterAction;
		setProtectionSettingsUI( protectionSettingsUI );
		setProtectionSettingsChanged( ( prev ) => ! prev );
	}, [
		settingUI,
		toggleState,
		filterAction,
		setProtectionSettingsChanged,
		protectionSettingsUI,
		setProtectionSettingsUI,
	] );

	const handleToggleChange = () => {
		setToggleState( ( value ) => ! value );
	};

	if ( ! protectionSettingsUI ) {
		return null;
	}

	// Render view.
	return (
		<div className="fraud-protection-rule-toggle">
			<strong>
				{ __( 'Enable filtering', 'woocommerce-payments' ) }
			</strong>
			<ToggleControl
				label={ label }
				key={ setting }
				help={ getHelpText( toggleState, filterAction ) }
				checked={ toggleState }
				className="fraud-protection-rule-toggle-toggle"
				onChange={ handleToggleChange }
			/>

			{ toggleState && (
				<div>
					{ children }
					<div className="fraud-protection-rule-toggle-block">
						<strong>
							{ __( 'Filter action', 'woocommerce-payments' ) }
						</strong>

						<RadioControl
							options={ radioOptions }
							selected={ filterAction }
							onChange={ setFilterAction }
						/>
					</div>
				</div>
			) }
		</div>
	);
};

export default FraudProtectionRuleToggle;
