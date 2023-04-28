/**
 * External dependencies
 */
import { Dispatch, SetStateAction } from 'react';

export interface FraudPreventionSetting {
	block: boolean;
	enabled: boolean;
	min_items?: string | number | null;
	max_items?: string | number | null;
	min_amount?: string | number | null;
	max_amount?: string | number | null;
}

export type ProtectionSettingsUI = Record< string, FraudPreventionSetting >;

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

export type UseCurrentProtectionLevel = [ string, ( level: string ) => void ];

export type UseAdvancedFraudPreventionSettings = [
	FraudProtectionRule[] | string,
	( settings: FraudProtectionRule[] ) => void
];

export interface UseSettings {
	isSaving: boolean;
	isLoading: boolean;
	saveSettings: () => void;
}

export function isFraudProtectionSettingsSingleCheck(
	check: FraudProtectionSettingsCheck
): check is FraudProtectionSettingsSingleCheck {
	return ( check as FraudProtectionSettingsSingleCheck ).key !== undefined;
}
