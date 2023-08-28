/**
 * Interface exports
 */

export type PaymentRequestEnabledSettingsHook = [
	boolean,
	( value: boolean ) => void
];

export type EnabledMethodIdsHook = [
	Array< string >,
	( value: Array< string > ) => void
];
