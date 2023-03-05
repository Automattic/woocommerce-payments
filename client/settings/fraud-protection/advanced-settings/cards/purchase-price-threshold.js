/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import AmountInput from 'wcpay/components/amount-input';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
import FraudPreventionSettingsContext from '../context';

const PurchasePriceThresholdCustomForm = ( { setting } ) => {
	const {
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	} = useContext( FraudPreventionSettingsContext );

	const [ minAmount, setMinAmount ] = useState(
		advancedFraudProtectionSettings[ setting ].min_amount ?? ''
	);
	const [ maxAmount, setMaxAmount ] = useState(
		advancedFraudProtectionSettings[ setting ].max_amount ?? ''
	);

	useEffect( () => {
		advancedFraudProtectionSettings[ setting ].min_amount = parseFloat(
			minAmount
		);
		advancedFraudProtectionSettings[ setting ].max_amount = parseFloat(
			maxAmount
		);
		setAdvancedFraudProtectionSettings( advancedFraudProtectionSettings );
	}, [
		setting,
		minAmount,
		maxAmount,
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	] );

	return (
		<div className="fraud-protection-rule-toggle-children-container">
			<strong>Limits</strong>
			<div className="fraud-protection-rule-toggle-children-horizontal-form">
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-minimum">
						{ __(
							'Minimum purchase price',
							'woocommerce-payments'
						) }
					</label>
					<AmountInput
						id={ 'fraud-protection-purchase-price-minimum' }
						prefix={ '$' }
						placeholder={ '0.00' }
						value={ minAmount }
						onChange={ ( val ) => {
							setMinAmount( val );
						} }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-maximum">
						{ __(
							'Maximum purchase price',
							'woocommerce-payments'
						) }
					</label>
					<AmountInput
						id={ 'fraud-protection-purchase-price-maximum' }
						prefix={ '$' }
						placeholder={ '0.00' }
						value={ maxAmount }
						onChange={ ( val ) => {
							setMaxAmount( val );
						} }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
			</div>
			{ ! parseFloat( minAmount ) && ! parseFloat( maxAmount ) && (
				<div>
					<br />
					<FraudProtectionRuleCardNotice type={ 'warning' }>
						{ __(
							'A price range must be set for this filter to take effect.',
							'woocommerce-payments'
						) }
					</FraudProtectionRuleCardNotice>
				</div>
			) }
			{ parseFloat( minAmount ) > parseFloat( maxAmount ) ? (
				<div>
					<br />
					<FraudProtectionRuleCardNotice type={ 'error' }>
						{ __(
							'Maximum purchase price must be greater than the minimum purchase price.',
							'woocommerce-payments'
						) }
					</FraudProtectionRuleCardNotice>
				</div>
			) : null }
		</div>
	);
};

const PurchasePriceThresholdRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Purchase Price Threshold', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the purchase price of an order to the minimum and maximum purchase amounts that you specify.',
			'woocommerce-payments'
		) }
	>
		<div>
			<FraudProtectionRuleToggle
				setting={ 'purchase_price_threshold' }
				label={ __(
					'Screen transactions for abnormal purchase prices',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			>
				<PurchasePriceThresholdCustomForm
					setting={ 'purchase_price_threshold' }
				/>
			</FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'An unusually high purchase amount, compared to the average for your business, ' +
						'can indicate potential fraudulent activity.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export const PurchasePriceThresholdValidation = (
	settings,
	setValidationError
) => {
	const key = 'purchase_price_threshold';
	if ( settings[ key ].enabled ) {
		if (
			! parseFloat( settings[ key ].min_amount ) &&
			! parseFloat( settings[ key ].max_amount )
		) {
			setValidationError(
				__(
					'A price range must be set for the "Purchase Price threshold" filter.',
					'woocommerce-payments'
				)
			);
			return false;
		}

		if (
			parseFloat( settings[ key ].min_amount ) >
			parseFloat( settings[ key ].max_amount )
		) {
			setValidationError(
				__(
					'Maximum purchase price must be greater than the minimum purchase price.',
					'woocommerce-payments'
				)
			);
			return false;
		}
	}
	return true;
};

export default PurchasePriceThresholdRuleCard;
