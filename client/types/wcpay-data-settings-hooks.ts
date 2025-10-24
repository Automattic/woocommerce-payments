/**
 * Using this module as a centralized way to type the settings-related hooks in `wcpay/data/settings/hooks`.
 * Those hooks are all written in JS. To gradually type them all, we can start typing them here as needed.
 * Once we have sufficient coverage and fixes, we should be able to convert the whole file.
 */
export type GenericSettingsHook< T > = [ T, ( value: T ) => void ];

export type StripeBillingMigrationState = [
	boolean,
	number,
	number,
	() => void,
	boolean,
	boolean
];

export type SettingsState = {
	isLoading: boolean;
	isSaving: boolean;
	isDirty: boolean;
	saveSettings: () => void;
};

interface FraudProtectionRule {
	key: string;
	outcome: string;
	check: FraudProtectionSettingsCheck;
}

type FraudProtectionSettingsCheck =
	| FraudProtectionSettingsSingleCheck
	| FraudProtectionSettingsMultipleChecks
	| null;

export interface FraudProtectionSettingsSingleCheck {
	key: string;
	value: any;
	operator: string;
}

export interface FraudProtectionSettingsMultipleChecks {
	operator: string;
	checks: FraudProtectionSettingsSingleCheck[];
}

export type AdvancedFraudPreventionSettingsState = [
	FraudProtectionRule[] | string,
	( settings: FraudProtectionRule[] ) => void
];

export interface SavingError {
	code?: string;
	message?: string;
	data?: {
		status?: number;
		params?: Record< string, string >;
		details?: Record<
			string,
			{
				code?: string;
				message?: string;
				data?: unknown;
			}
		>;
	};
}
