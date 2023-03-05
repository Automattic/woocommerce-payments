/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
import FraudProtectionRuleDescription from '../rule-description';
import FraudPreventionSettingsContext from '../context';

const OrderItemsThresholdCustomForm = ( { setting } ) => {
	const {
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	} = useContext( FraudPreventionSettingsContext );

	const [ minItemsCount, setMinItemsCount ] = useState(
		advancedFraudProtectionSettings[ setting ].min_items ?? 0
	);
	const [ maxItemsCount, setMaxItemsCount ] = useState(
		advancedFraudProtectionSettings[ setting ].max_items ?? 0
	);

	useEffect( () => {
		advancedFraudProtectionSettings[ setting ].min_items = parseInt(
			minItemsCount,
			10
		);
		advancedFraudProtectionSettings[ setting ].max_items = parseInt(
			maxItemsCount,
			10
		);
		setAdvancedFraudProtectionSettings( advancedFraudProtectionSettings );
	}, [
		setting,
		minItemsCount,
		maxItemsCount,
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
							'Minimum items per order',
							'woocommerce-payments'
						) }
					</label>
					<TextControl
						id={ 'fraud-protection-purchase-price-minimum' }
						placeholder={ '0' }
						value={ minItemsCount }
						onChange={ setMinItemsCount }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-maximum">
						{ __(
							'Maximum items per order',
							'woocommerce-payments'
						) }
					</label>
					<TextControl
						id={ 'fraud-protection-purchase-price-maximum' }
						prefix={ '$' }
						placeholder={ '0' }
						value={ maxItemsCount }
						onChange={ setMaxItemsCount }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
			</div>
			{ ! parseInt( minItemsCount, 10 ) &&
				! parseInt( maxItemsCount, 10 ) && (
					<div>
						<br />
						<FraudProtectionRuleCardNotice type={ 'warning' }>
							{ __(
								'An item range must be set for this filter to take effect.',
								'woocommerce-payments'
							) }
						</FraudProtectionRuleCardNotice>
					</div>
				) }
			{ parseInt( minItemsCount, 10 ) > parseInt( maxItemsCount, 10 ) ? (
				<div>
					<br />
					<FraudProtectionRuleCardNotice type={ 'error' }>
						{ __(
							'Maximum item count must be greater than the minimum item count.',
							'woocommerce-payments'
						) }
					</FraudProtectionRuleCardNotice>
				</div>
			) : null }
		</div>
	);
};
const OrderItemsThresholdRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Order Items Threshold', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the amount of items in an order to the minimum and maximum counts that you specify.',
			'woocommerce-payments'
		) }
	>
		<div>
			<FraudProtectionRuleToggle
				setting={ 'order_items_threshold' }
				label={ __(
					'Screen transactions for abnormal item counts',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			>
				<OrderItemsThresholdCustomForm
					setting={ 'order_items_threshold' }
				/>
			</FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'An unusually high item count, compared to the average for your business, can indicate potential fraudulent activity.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export const OrderItemsThresholdValidation = (
	settings,
	setValidationError
) => {
	const key = 'order_items_threshold';
	if ( settings[ key ].enabled ) {
		if (
			! parseInt( settings[ key ].min_items, 10 ) &&
			! parseInt( settings[ key ].max_items, 10 )
		) {
			setValidationError(
				__(
					'An item range must be set for the "Order Item Threshold" filter.',
					'woocommerce-payments'
				)
			);
			return false;
		}
		if (
			parseInt( settings[ key ].min_items, 10 ) >
			parseInt( settings[ key ].max_items, 10 )
		) {
			setValidationError(
				__(
					'Maximum item count must be greater than the minimum item count on the "Order Item Threshold" rule.',
					'woocommerce-payments'
				)
			);
			return false;
		}
	}
	return true;
};

export default OrderItemsThresholdRuleCard;
