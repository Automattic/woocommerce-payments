/**
 * Interface exports
 */

export type StripeBillingHook = [ boolean, ( value: boolean ) => void ];

export type StripeBillingMigrationHook = [
	boolean,
	number,
	number,
	() => void,
	boolean,
	boolean
];
