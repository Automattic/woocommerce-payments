/**
 * External dependencies
 */
import React, {
	useContext,
	useEffect,
	useState,
	useMemo,
	Dispatch,
	SetStateAction,
} from 'react';
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
import {
	FraudPreventionOrderItemsThresholdSetting,
	FraudPreventionSettings,
	isOrderItemsThresholdSetting,
} from '../../interfaces';

interface OrderItemsThresholdCustomFormProps {
	setting: string;
}

const OrderItemsThresholdCustomForm: React.FC< OrderItemsThresholdCustomFormProps > = ( {
	setting,
} ) => {
	const {
		protectionSettingsUI,
		setProtectionSettingsUI,
		setProtectionSettingsChanged,
		setHasChanges,
	} = useContext( FraudPreventionSettingsContext );

	const settingUI = useMemo(
		() =>
			protectionSettingsUI[
				setting
			] as FraudPreventionOrderItemsThresholdSetting,
		[ protectionSettingsUI, setting ]
	);

	const minItemsTemp = parseInt( settingUI.min_items + '', 10 );
	const maxItemsTemp = parseInt( settingUI.max_items + '', 10 );

	const [ minItemsCount, setMinItemsCount ] = useState(
		isNaN( minItemsTemp ) ? '' : minItemsTemp
	);
	const [ maxItemsCount, setMaxItemsCount ] = useState(
		isNaN( maxItemsTemp ) ? '' : maxItemsTemp
	);

	useEffect( () => {
		settingUI.min_items = minItemsCount
			? parseInt( minItemsCount + '', 10 )
			: minItemsCount;
		settingUI.max_items = maxItemsCount
			? parseInt( maxItemsCount + '', 10 )
			: maxItemsCount;
		setProtectionSettingsUI( protectionSettingsUI );
		setProtectionSettingsChanged( ( prev ) => ! prev );
	}, [
		settingUI,
		minItemsCount,
		maxItemsCount,
		protectionSettingsUI,
		setProtectionSettingsUI,
		setProtectionSettingsChanged,
	] );

	const isItemRangeEmpty =
		! parseInt( minItemsCount + '', 10 ) &&
		! parseInt( maxItemsCount + '', 10 );
	const isMinGreaterThanMax =
		parseInt( minItemsCount + '', 10 ) > parseInt( maxItemsCount + '', 10 );

	return (
		<div className="fraud-protection-rule-toggle-children-container">
			<strong>Limits</strong>
			<div className="fraud-protection-rule-toggle-children-horizontal-form">
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-order-items-minimum">
						{ __(
							'Minimum items per order',
							'woocommerce-payments'
						) }
					</label>
					<TextControl
						id={ 'fraud-protection-order-items-minimum' }
						placeholder={ '0' }
						value={ minItemsCount }
						type="number"
						onChange={ ( value ) => {
							setMinItemsCount( value );
							setHasChanges( true );
						} }
						onKeyDown={ ( e ) =>
							/^[+-.,e]$/m.test( e.key ) && e.preventDefault()
						}
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
						min="1"
						step="1"
					/>
				</div>
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-order-items-maximum">
						{ __(
							'Maximum items per order',
							'woocommerce-payments'
						) }
					</label>
					<TextControl
						id={ 'fraud-protection-order-items-maximum' }
						placeholder={ '0' }
						type="number"
						value={ maxItemsCount }
						onChange={ ( value ) => {
							setMaxItemsCount( value );
							setHasChanges( true );
						} }
						onKeyDown={ ( e ) =>
							/^[+-.,e]$/m.test( e.key ) && e.preventDefault()
						}
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
						min="1"
						step="1"
					/>
				</div>
			</div>
			{ isItemRangeEmpty && (
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
			{ isMinGreaterThanMax ? (
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
const OrderItemsThresholdRuleCard: React.FC = () => (
	<FraudProtectionRuleCard
		title={ __( 'Order Items Threshold', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the amount of items in an order to the minimum and maximum counts that you specify.',
			'woocommerce-payments'
		) }
		id="order-items-threshold-card"
	>
		<FraudProtectionRuleToggle
			setting={ 'order_items_threshold' }
			label={ __(
				'Block transactions for abnormal item counts',
				'woocommerce-payments'
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
	</FraudProtectionRuleCard>
);

export const OrderItemsThresholdValidation = (
	setting: FraudPreventionSettings,
	setValidationError: Dispatch< SetStateAction< string | null > >
): boolean => {
	if ( setting.enabled && isOrderItemsThresholdSetting( setting ) ) {
		const { min_items: minItems, max_items: maxItems } = setting;

		if (
			! parseInt( minItems + '', 10 ) &&
			! parseInt( maxItems + '', 10 )
		) {
			setValidationError(
				__(
					'An item range must be set for the "Order Item Threshold" filter.',
					'woocommerce-payments'
				)
			);
			return false;
		}
		if ( parseInt( minItems + '', 10 ) > parseInt( maxItems + '', 10 ) ) {
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
