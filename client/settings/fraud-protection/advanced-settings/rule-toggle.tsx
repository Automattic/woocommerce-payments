/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './rule-toggle.scss';
import { ToggleControl } from 'wcpay/components/wp-components-wrapped/components/toggle-control';
import { RadioControl } from 'wcpay/components/wp-components-wrapped/components/radio-control';
import FraudPreventionSettingsContext from './context';
import { FraudPreventionSettings } from '../interfaces';

interface FraudProtectionRuleToggleProps {
	setting: string;
	label: string;
	description: React.ReactNode;
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

const getFilterAction = (
	settingUI: FraudPreventionSettings,
	isFRTReviewFeatureActive: boolean
) => {
	if ( ! isFRTReviewFeatureActive ) return filterActions.BLOCK;

	return settingUI.block ? filterActions.BLOCK : filterActions.REVIEW;
};

const FraudProtectionRuleToggle: React.FC< React.PropsWithChildren<
	FraudProtectionRuleToggleProps
> > = ( { setting, label, description, children } ) => {
	const {
		protectionSettingsUI,
		setProtectionSettingsUI,
		setIsDirty,
	} = useContext( FraudPreventionSettingsContext );

	const { isFRTReviewFeatureActive } = wcpaySettings.featureFlags;

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
		<>
			<div className="fraud-protection-rule-toggle">
				<ToggleControl
					label={ label }
					key={ setting }
					checked={ settingUI?.enabled }
					help={ description }
					onChange={ handleEnableToggleChange }
					__nextHasNoMarginBottom
				/>
			</div>

			{ settingUI?.enabled && (
				<>
					{ children }
					{ Boolean( isFRTReviewFeatureActive ) && (
						<div>
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
								__nextHasNoMarginBottom
							/>
						</div>
					) }
				</>
			) }
		</>
	);
};

export default FraudProtectionRuleToggle;
