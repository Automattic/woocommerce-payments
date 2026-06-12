/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useEffect } from '@wordpress/element';
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
 * components provide. The registered components exist only because the design
 * requires control types the SDK's native field renderers don't offer yet
 * (ToggleControl for booleans, per-option radio descriptions) — each one is an
 * upstream SDK feature ask, tracked in the spike findings.
 *
 * Soft tab navigation: the schema contains every design tab's groups, and
 * registered `groupVisibility` predicates toggle them based on the hidden
 * `wcpay_active_tab` field value, so switching tabs swaps content without a
 * page load. Navigation regions cannot mutate form state, so the header tab
 * bar dispatches a DOM event that the hidden field component (which owns
 * `onChange`) listens for — itself evidence for an upstream ask: the SDK has
 * no client-side section routing and no dirty-exempt "UI state" values, which
 * is why switching tabs spuriously enables the Save button.
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

interface SpikeTab {
	id: string;
	label: string;
	href: string;
	active?: boolean;
}

interface RegionComponentProps {
	values: SettingsValues;
	schema: {
		shell?: {
			wcpayTabs?: SpikeTab[];
		};
	};
}

interface VisibilityPredicateArgs {
	values: SettingsValues;
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
	regions?: Record< string, React.ComponentType< RegionComponentProps > >;
	groupVisibility?: Record<
		string,
		( args: VisibilityPredicateArgs ) => boolean
	>;
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

// eslint-disable-next-line @typescript-eslint/naming-convention
const TAB_CHANGE_EVENT = 'wcpay:spike-tab-change';
// eslint-disable-next-line @typescript-eslint/naming-convention
const DEFAULT_TAB = 'general';
// eslint-disable-next-line @typescript-eslint/naming-convention
const TAB_IDS = [
	'general',
	'payment-methods',
	'payouts',
	'store-and-checkout',
];

/**
 * Fields the save handler is allowed to send to /wc/v3/payments/settings.
 * Anything else in the schema (the VAT toggle without REST backing, the
 * wcpay_active_tab UI state) is rendered but never persisted.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention
const PERSISTABLE_FIELDS = [
	'is_test_mode_enabled',
	'current_protection_level',
	'account_communications_email',
	'is_debug_log_enabled',
];

const getActiveTab = ( values: SettingsValues ): string => {
	const value = values.wcpay_active_tab;
	return typeof value === 'string' && TAB_IDS.includes( value )
		? value
		: DEFAULT_TAB;
};

const getTabFromUrl = (): string => {
	const tab = new URLSearchParams( window.location.search ).get(
		'wcpay_tab'
	);
	return tab && TAB_IDS.includes( tab ) ? tab : DEFAULT_TAB;
};

const onTab =
	( tab: string ) =>
	( { values }: VisibilityPredicateArgs ) =>
		getActiveTab( values ) === tab;

/**
 * Hidden form-state carrier for the active design tab. Renders nothing; it
 * exists because only field components receive state setters — the navigation
 * region cannot mutate values, so it dispatches a DOM event handled here.
 */
const SpikeTabState: React.FC< FieldComponentProps > = ( {
	value,
	onChange,
} ) => {
	useEffect( () => {
		const handleTabChange = ( event: Event ) => {
			const tab = ( event as CustomEvent< string > ).detail;
			if ( ! TAB_IDS.includes( tab ) || tab === value ) {
				return;
			}

			onChange( tab );

			const url = new URL( window.location.href );
			if ( tab === DEFAULT_TAB ) {
				url.searchParams.delete( 'wcpay_tab' );
			} else {
				url.searchParams.set( 'wcpay_tab', tab );
			}
			window.history.pushState( {}, '', url );
		};

		const handlePopState = () => {
			const tab = getTabFromUrl();
			if ( tab !== value ) {
				onChange( tab );
			}
		};

		window.addEventListener( TAB_CHANGE_EVENT, handleTabChange );
		window.addEventListener( 'popstate', handlePopState );

		return () => {
			window.removeEventListener( TAB_CHANGE_EVENT, handleTabChange );
			window.removeEventListener( 'popstate', handlePopState );
		};
	}, [ value, onChange ] );

	return null;
};

/**
 * Soft tab clicks must be claimed before the SDK's unsaved-changes link
 * interceptor, which listens on document at capture phase and prompts for any
 * `a[href]` click while the form is dirty — and tab switching itself marks the
 * form dirty (the wcpay_active_tab wart). The interceptor skips events whose
 * default is already prevented, and same-phase document listeners run in
 * registration order, so registering at module load (before the SDK's
 * dirty-effect adds its listener) wins deterministically.
 *
 * Modifier/middle clicks are left alone so open-in-new-tab still full-loads.
 */
document.addEventListener(
	'click',
	( event ) => {
		if (
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		const target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}

		const tabLink = target.closest( '[data-wcpay-spike-tab]' );
		if ( ! ( tabLink instanceof HTMLElement ) ) {
			return;
		}

		event.preventDefault();
		window.dispatchEvent(
			new CustomEvent< string >( TAB_CHANGE_EVENT, {
				detail: tabLink.dataset.wcpaySpikeTab || '',
			} )
		);
	},
	true
);

/**
 * Design: secondary tab bar in the page header. Reuses Core's
 * `wc-settings-ui-shell__tabs` classes so Core owns the styling. Clicks are
 * soft navigations (capture listener above → SpikeTabState → groupVisibility);
 * the hrefs stay real so middle-click / open-in-new-tab still work as full
 * loads.
 */
const SpikeSubnav: React.FC< RegionComponentProps > = ( {
	values,
	schema,
} ) => {
	const tabs = schema.shell?.wcpayTabs || [];
	const activeTab = getActiveTab( values );

	if ( tabs.length === 0 ) {
		return null;
	}

	return (
		<nav
			className="wc-settings-ui-shell__tabs wc-settings-ui-shell__tabs--secondary"
			aria-label={ __(
				'WooPayments settings tabs',
				'woocommerce-payments'
			) }
		>
			{ tabs.map( ( tab ) => (
				<a
					key={ tab.id }
					className={
						tab.id === activeTab
							? 'wc-settings-ui-shell__tab is-active'
							: 'wc-settings-ui-shell__tab'
					}
					href={ tab.href }
					data-wcpay-spike-tab={ tab.id }
					aria-current={ tab.id === activeTab ? 'page' : undefined }
				>
					{ tab.label }
				</a>
			) ) }
		</nav>
	);
};

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
			'wcpay/tab-state': SpikeTabState,
			'wcpay/toggle': SpikeToggle,
			'wcpay/protection-level': SpikeProtectionLevel,
		},
		regions: {
			'wcpay/subnav': SpikeSubnav,
		},
		groupVisibility: {
			wcpay_test_mode: onTab( 'general' ),
			wcpay_fraud_protection: onTab( 'general' ),
			wcpay_tax_id: onTab( 'general' ),
			wcpay_account_notifications: onTab( 'general' ),
			wcpay_debug_mode: onTab( 'general' ),
			wcpay_tab_payment_methods: onTab( 'payment-methods' ),
			wcpay_tab_payouts: onTab( 'payouts' ),
			wcpay_tab_store_and_checkout: onTab( 'store-and-checkout' ),
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
