/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import { getHelpText, filterActions } from '../rule-toggle';
import { useAVSMismatchSettings } from 'wcpay/data';

const AVSMismatchRuleCard = () => {
	const [ settingState, setSettingState ] = useAVSMismatchSettings();
	const [ toggleState, setToggleState ] = useState( settingState );

	useEffect( () => {
		setSettingState( toggleState );
	}, [ setSettingState, toggleState ] );

	const handleToggleChange = () => {
		setToggleState( ( value ) => ! value );
	};

	return (
		<FraudProtectionRuleCard
			title={ __( 'AVS Mismatch', 'woocommerce-payments' ) }
			description={ __(
				'This filter compares the street number and the post code submitted by the customer against the data on ' +
					'file with the card issuer.',
				'woocommerce-payments'
			) }
			id="avs-mismatch-card"
		>
			<div className="fraud-protection-rule-toggle">
				<strong>
					{ __( 'Enable filtering', 'woocommerce-payments' ) }
				</strong>
				<ToggleControl
					label={ __(
						'Screen transactions for mismatched AVS',
						'woocommerce-payments'
					) }
					key="avs-mismatch"
					help={ getHelpText( toggleState, filterActions.BLOCK ) }
					checked={ toggleState }
					className="fraud-protection-rule-toggle-toggle"
					onChange={ handleToggleChange }
				/>
			</div>

			<FraudProtectionRuleDescription>
				{ __(
					'Buyers who can provide the street number and post code on file with the issuing bank ' +
						'are more likely to be the actual account holder. AVS matches, however, are not a guarantee.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</FraudProtectionRuleCard>
	);
};

export default AVSMismatchRuleCard;
