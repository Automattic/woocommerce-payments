/**
 * External dependencies
 */
import React, {
	useContext,
	useEffect,
	useState,
	useMemo,
	SetStateAction,
	Dispatch,
} from 'react';
import { __ } from '@wordpress/i18n';
import AmountInput from 'wcpay/components/amount-input';

/**
 * Internal dependencies
 */
import { getCurrency } from 'utils/currency';
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
import FraudPreventionSettingsContext from '../context';
import {
	FraudPreventionPurchasePriceThresholdSetting,
	FraudPreventionSettings,
	isPurchasePriceThresholdSetting,
} from '../../interfaces';

const getFloatValue = ( value: string ) => {
	return '' === value || '0' === value ? 0 : parseFloat( value );
};

const getCurrencySymbol = () => {
	const fallbackCurrency = { symbol: '$' };

	if ( '1' !== wcpaySettings.isMultiCurrencyEnabled ) {
		return fallbackCurrency.symbol;
	}

	const currency = getCurrency( wcpaySettings.storeCurrency );
	const { symbol } =
		( currency as any )?.getCurrencyConfig() || fallbackCurrency;

	return symbol;
};

interface PurchasePriceThresholdCustomFormProps {
	setting: string;
}

const PurchasePriceThresholdCustomForm: React.FC< PurchasePriceThresholdCustomFormProps > = ( {
	setting,
} ) => {
	const { protectionSettingsUI, setProtectionSettingsUI } = useContext(
		FraudPreventionSettingsContext
	);

	const settingUI = useMemo(
		() =>
			protectionSettingsUI[
				setting
			] as FraudPreventionPurchasePriceThresholdSetting,
		[ protectionSettingsUI, setting ]
	);

	const minAmountTemp = parseFloat( settingUI.min_amount + '' );
	const maxAmountTemp = parseFloat( settingUI.max_amount + '' );

	const [ minAmount, setMinAmount ] = useState( minAmountTemp ?? '' );
	const [ maxAmount, setMaxAmount ] = useState( maxAmountTemp ?? '' );

	useEffect( () => {
		settingUI.min_amount = minAmount;
		settingUI.max_amount = maxAmount;
		setProtectionSettingsUI( protectionSettingsUI );
	}, [
		minAmount,
		maxAmount,
		protectionSettingsUI,
		setProtectionSettingsUI,
		settingUI,
	] );

	const areInputsEmpty =
		! getFloatValue( minAmount + '' ) && ! getFloatValue( maxAmount + '' );
	const isMinGreaterThanMax =
		minAmount &&
		maxAmount &&
		getFloatValue( minAmount + '' ) > getFloatValue( maxAmount + '' );

	const currencySymbol = getCurrencySymbol();

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
						prefix={ currencySymbol }
						placeholder={ '0.00' }
						value={ minAmount.toString() }
						onChange={ ( val ) => setMinAmount( Number( val ) ) }
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
						prefix={ currencySymbol }
						placeholder={ '0.00' }
						value={ maxAmount.toString() }
						onChange={ ( val ) => setMaxAmount( Number( val ) ) }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
			</div>
			{ areInputsEmpty && (
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
			{ isMinGreaterThanMax ? (
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

const PurchasePriceThresholdRuleCard: React.FC = () => (
	<FraudProtectionRuleCard
		title={ __( 'Purchase Price Threshold', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the purchase price of an order to the minimum and maximum purchase amounts that you specify.',
			'woocommerce-payments'
		) }
		id="purchase-price-threshold-card"
	>
		<FraudProtectionRuleToggle
			setting={ 'purchase_price_threshold' }
			label={ __(
				'Block transactions for abnormal purchase prices',
				'woocommerce-payments'
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
	</FraudProtectionRuleCard>
);

export const PurchasePriceThresholdValidation = (
	setting: FraudPreventionSettings,
	setValidationError: Dispatch< SetStateAction< string | null > >
): boolean => {
	if ( setting.enabled && isPurchasePriceThresholdSetting( setting ) ) {
		const { min_amount: minAmount, max_amount: maxAmount } = setting;

		const minAmountFloat = getFloatValue( minAmount + '' );
		const maxAmountFloat = getFloatValue( maxAmount + '' );

		if ( ! minAmountFloat && ! maxAmountFloat ) {
			setValidationError(
				__(
					'A price range must be set for the "Purchase Price threshold" filter.',
					'woocommerce-payments'
				)
			);
			return false;
		}

		if ( minAmount && maxAmount && minAmountFloat > maxAmountFloat ) {
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
