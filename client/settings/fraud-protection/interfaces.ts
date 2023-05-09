/**
 * External dependencies
 */
import { Dispatch, SetStateAction } from 'react';

export interface FraudPreventionSetting {
	block: boolean;
	enabled: boolean;
}

export interface OrderItemsThresholdSetting {
	min_items: string | number | null;
	max_items: string | number | null;
}

export interface PurchasePriceThresholdSetting {
	min_amount: string | number | null;
	max_amount: string | number | null;
}

export type FraudPreventionOrderItemsThresholdSetting = FraudPreventionSetting &
	OrderItemsThresholdSetting;

export type FraudPreventionPurchasePriceThresholdSetting = FraudPreventionSetting &
	PurchasePriceThresholdSetting;

export type FraudPreventionSettings =
	| FraudPreventionSetting
	| FraudPreventionOrderItemsThresholdSetting
	| FraudPreventionPurchasePriceThresholdSetting;

export type ProtectionSettingsUI = Record< string, FraudPreventionSettings >;

export interface FraudPreventionSettingsContextType {
	protectionSettingsUI: ProtectionSettingsUI;
	setProtectionSettingsUI: ( settings: ProtectionSettingsUI ) => void;
	protectionSettingsChanged: boolean;
	setProtectionSettingsChanged: Dispatch< SetStateAction< boolean > >;
}

export interface FraudProtectionSettingsSingleCheck {
	key: string;
	value: any;
	operator: string;
}

export interface FraudProtectionSettingsMultipleChecks {
	operator: string;
	checks: FraudProtectionSettingsSingleCheck[];
}

export type FraudProtectionSettingsCheck =
	| FraudProtectionSettingsSingleCheck
	| FraudProtectionSettingsMultipleChecks
	| null;

export interface FraudProtectionRule {
	key: string;
	outcome: string;
	check: FraudProtectionSettingsCheck;
}

export type CurrentProtectionLevelHook = [ string, ( level: string ) => void ];

export type AdvancedFraudPreventionSettingsHook = [
	FraudProtectionRule[] | string,
	( settings: FraudProtectionRule[] ) => void
];

export interface SettingsHook {
	isSaving: boolean;
	isLoading: boolean;
	saveSettings: () => void;
}

export function isFraudProtectionSettingsSingleCheck(
	check: FraudProtectionSettingsCheck
): check is FraudProtectionSettingsSingleCheck {
	return ( check as FraudProtectionSettingsSingleCheck ).key !== undefined;
}

export function isOrderItemsThresholdSetting(
	setting: FraudPreventionSetting
): setting is FraudPreventionOrderItemsThresholdSetting {
	return (
		( setting as FraudPreventionOrderItemsThresholdSetting ).min_items !==
		undefined
	);
}

export function isPurchasePriceThresholdSetting(
	setting: FraudPreventionSetting
): setting is FraudPreventionPurchasePriceThresholdSetting {
	return (
		( setting as FraudPreventionPurchasePriceThresholdSetting )
			.min_amount !== undefined
	);
}
