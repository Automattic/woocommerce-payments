/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	ExternalLink,
	RadioControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * WOOPMNT-6211 spike: WooPayments settings rendered through the WooCommerce
 * Settings UI SDK (Modernised WooPayments Settings i1 designs).
 *
 * SDK-native on purpose: no custom CSS, no markup beyond what the WordPress
 * components provide. The registered components exist only because
 * the design requires control types the SDK's native field renderers don't
 * offer yet (ToggleControl for booleans, per-option radio descriptions) —
 * each one is an upstream SDK feature ask, tracked in the spike findings.
 *
 * Registration goes through the `window.wcSettingsUI` global set by the
 * `wc-settings-ui-sdk` script (a declared dependency of this bundle).
 */

type SettingsValue = string | number | boolean | string[] | null;
type SettingsValues = Record< string, SettingsValue >;

interface SettingsField {
	id: string;
	label: string;
	type: string;
	description?: string;
	value?: SettingsValue;
	disabled?: boolean;
	options?: Array< { label: string; value: string } >;
	customAttributes?: Record< string, string | number | boolean >;
}

interface FieldComponentProps {
	field: SettingsField;
	value: SettingsValue;
	onChange: ( value: SettingsValue ) => void;
}

interface SettingsSaveHandlerArgs {
	values: SettingsValues;
	initialValues: SettingsValues;
	changedValues: Partial< SettingsValues >;
	dirtyFields: string[];
	context: { page: string; section?: string };
}

interface SettingsExtensionRegistration {
	scope: { page: string; section?: string };
	components?: Record< string, React.ComponentType< FieldComponentProps > >;
	saveHandlers?: Record<
		string,
		(
			args: SettingsSaveHandlerArgs
		) => Promise< void | { values?: SettingsValues; notice?: string } >
	>;
}

declare global {
	interface Window {
		wcSettingsUI?: {
			registerSettingsExtension: (
				registration: SettingsExtensionRegistration
			) => void;
		};
	}
}

/**
 * Fields the save handler is allowed to send to /wc/v3/payments/settings.
 * Anything else in the schema (e.g. the VAT toggle, which has no REST backing
 * yet) is rendered but never persisted.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention
const PERSISTABLE_FIELDS = [
	'is_test_mode_enabled',
	'current_protection_level',
	'account_communications_email',
	'is_debug_log_enabled',
];

/**
 * Design: boolean settings render as toggles. The SDK's native checkbox
 * renderer uses CheckboxControl, so this maps the field onto a plain
 * ToggleControl with its native `help` prop (plus an optional external
 * "Learn more" link). Upstream ask: a native `toggle` field type.
 */
const SpikeToggle: React.FC< FieldComponentProps > = ( {
	field,
	value,
	onChange,
} ) => {
	const learnMoreUrl = field.customAttributes?.learnMoreUrl as
		| string
		| undefined;

	const help = field.description ? (
		<>
			{ field.description }{ ' ' }
			{ learnMoreUrl && (
				<ExternalLink href={ learnMoreUrl }>
					{ __( 'Learn more', 'woocommerce-payments' ) }
				</ExternalLink>
			) }
		</>
	) : undefined;

	return (
		<ToggleControl
			__nextHasNoMarginBottom
			checked={ !! value }
			disabled={ !! field.disabled }
			label={ field.label }
			help={ help }
			onChange={ ( checked: boolean ) => onChange( checked ) }
		/>
	);
};

/**
 * Design: radio options with per-option help text. WP RadioControl supports
 * this natively via option `description`; the SDK's canonical option shape
 * doesn't carry descriptions yet, so they travel in customAttributes.
 * Upstream ask: per-option descriptions in the native radio renderer.
 */
const SpikeProtectionLevel: React.FC< FieldComponentProps > = ( {
	field,
	value,
	onChange,
} ) => {
	const helpByValue: Record< string, string | undefined > = {
		basic: field.customAttributes?.helpBasic as string | undefined,
		advanced: field.customAttributes?.helpAdvanced as string | undefined,
	};

	return (
		<RadioControl
			label={ field.label }
			selected={ ( value as string ) ?? '' }
			options={ ( field.options || [] ).map( ( option ) => ( {
				...option,
				description: helpByValue[ option.value ],
			} ) ) }
			onChange={ ( next: string ) => onChange( next ) }
		/>
	);
};

const registry = window.wcSettingsUI;

if ( registry ) {
	registry.registerSettingsExtension( {
		scope: { page: 'woocommerce_payments' },
		components: {
			'wcpay/toggle': SpikeToggle,
			'wcpay/protection-level': SpikeProtectionLevel,
		},
		saveHandlers: {
			// eslint-disable-next-line @typescript-eslint/naming-convention
			'wcpay-rest-settings': async ( { changedValues } ) => {
				const data: Partial<
					SettingsValues & {
						advanced_fraud_protection_settings: unknown[];
					}
				> = {};
				for ( const key of PERSISTABLE_FIELDS ) {
					if ( key in changedValues ) {
						data[ key ] = changedValues[ key ];
					}
				}

				// The REST controller only applies current_protection_level
				// when advanced_fraud_protection_settings is also present
				// (compound save contract — see spike findings). The spike
				// sends an empty ruleset; a real integration must source it
				// from the fraud settings store.
				if ( 'current_protection_level' in data ) {
					data.advanced_fraud_protection_settings = [];
				}

				if ( Object.keys( data ).length > 0 ) {
					await apiFetch( {
						path: '/wc/v3/payments/settings',
						method: 'POST',
						data,
					} );
				}

				return {
					notice: __(
						'WooPayments settings saved.',
						'woocommerce-payments'
					),
				};
			},
		},
	} );
} else {
	// eslint-disable-next-line no-console
	console.warn(
		'[WooPayments settings UI spike] wc-settings-ui-sdk registry is unavailable.'
	);
}
