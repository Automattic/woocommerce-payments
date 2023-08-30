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

export type WooPayEnabledSettingsHook = [ boolean, ( value: boolean ) => void ];
