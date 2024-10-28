/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { ToggleControl, RadioControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './../style.scss';
import FraudPreventionSettingsContext from './context';
import { FraudPreventionSettings } from '../interfaces';

interface FraudProtectionRuleToggleProps {
	setting: string;
	label: string;
}

export const filterActions = {
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
	unchecked: __( 'When enabled, the payment will be blocked.' ),
	[ filterActions.REVIEW ]: __(
		'The payment method will not be charged until you review and approve the transaction.'
	),
	[ filterActions.BLOCK ]: __( 'The payment will be blocked.' ),
};

export const getHelpText = (
	toggleState: boolean,
	filterAction: string
): string => {
	if ( ! toggleState ) return helpTextMapping.unchecked;

	return helpTextMapping[ filterAction ];
};

const getFilterAction = (
	settingUI: FraudPreventionSettings,
	isFRTReviewFeatureActive: boolean
) => {
	if ( ! isFRTReviewFeatureActive ) return filterActions.BLOCK;

	return settingUI.block ? filterActions.BLOCK : filterActions.REVIEW;
};

const FraudProtectionRuleToggle: React.FC< FraudProtectionRuleToggleProps > = ( {
	setting,
	label,
	children,
} ) => {
	const {
		protectionSettingsUI,
		setProtectionSettingsUI,
		setIsDirty,
	} = useContext( FraudPreventionSettingsContext );

	const { isFRTReviewFeatureActive } = wcpaySettings;

	const settingUI = protectionSettingsUI?.[ setting ];
	const filterAction = getFilterAction( settingUI, isFRTReviewFeatureActive );

	const handleToggleChange = ( field: string, value: boolean ) => {
		setProtectionSettingsUI( ( settings ) => ( {
			...settings,
			[ setting ]: {
				...settings[ setting ],
				[ field ]: value,
			},
		} ) );
		setIsDirty( true );
	};

	const handleEnableToggleChange = ( value: boolean ) => {
		handleToggleChange( 'enabled', value );
	};

	const handleBlockToggleChange = ( value: string ) => {
		handleToggleChange( 'block', filterActions.BLOCK === value );
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
				help={ getHelpText( settingUI?.enabled, filterAction ) }
				checked={ settingUI?.enabled }
				className="fraud-protection-rule-toggle-toggle"
				onChange={ handleEnableToggleChange }
			/>

			{ settingUI?.enabled && (
				<div>
					{ children }

					{ !! isFRTReviewFeatureActive && (
						<div className="fraud-protection-rule-toggle-block">
							<strong>
								{ __(
									'Filter action',
									'woocommerce-payments'
								) }
							</strong>

							<RadioControl
								options={ radioOptions }
								selected={ filterAction }
								onChange={ handleBlockToggleChange }
							/>
						</div>
					) }
				</div>
			) }
		</div>
	);
};

export default FraudProtectionRuleToggle;
