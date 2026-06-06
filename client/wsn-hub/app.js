/**
 * WsnHubApp — the top-level component for the Woo Shopping Network Hub page.
 *
 * Hosts the 2-tab framework (Overview / Profile) using @wordpress/components'
 * <TabPanel> with hash-based deep-linking so /shopping-network#profile lands on the
 * Profile tab directly.
 *
 * Visibility was removed 2026-06-05 — product exposure to the ES index now
 * relies on WC's native product-catalog-visibility settings instead of a
 * WSN-specific opt-in surface. See the architecture doc for the rationale.
 *
 * Pre-enable state (RSM-2493): when `wcpaySettings.wsn?.enabled` is falsy, the tab nav
 * is hidden and only the Overview tab's PreEnableHero renders, taking over the full
 * page. The merchant clicks "Enable Woo Shopping Network" on the hero, which flips
 * the option via the settings PUT endpoint. On success, OverviewTab calls
 * `onEnabledChange(true)` which re-renders this shell with the tab nav visible.
 *
 * Owns the /wsn/settings fetch: the shell loads settings + derivations once on
 * mount and passes both down to ProfileTab. Switching between Overview and
 * Profile no longer re-fetches — ProfileTab consumes shell-owned state and
 * calls `refreshSettings` after a successful save so the shell re-reads the
 * server-resolved derivations.
 *
 * @format
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import PageHeader from './page-header';
import OverviewTab from './overview-tab';
import ProfileTab from './profile-tab';
import { colors, spacing } from './tokens';
import { TabErrorBoundary } from './utils/error-boundary';
import { formatApiError } from './utils/format-api-error';

const TABS = [
	{ name: 'overview', title: __( 'Overview', 'woocommerce-payments' ) },
	{ name: 'profile', title: __( 'Profile', 'woocommerce-payments' ) },
];

const TAB_NAMES = TABS.map( ( tab ) => tab.name );

const getInitialTabName = () => {
	if ( typeof window === 'undefined' ) {
		return TABS[ 0 ].name;
	}
	const hash = window.location.hash.replace( '#', '' );
	return TAB_NAMES.includes( hash ) ? hash : TABS[ 0 ].name;
};

const getInitialEnabled = () => {
	if ( typeof window === 'undefined' ) {
		return false;
	}
	return Boolean( window.wcpaySettings?.wsn?.enabled );
};

const WsnHubApp = () => {
	// useState seeds from the hash on first render; subsequent hash changes update via
	// the effect below so back/forward navigation works.
	const [ currentTab, setCurrentTab ] = useState( getInitialTabName );
	const [ isEnabled, setIsEnabled ] = useState( getInitialEnabled );

	// Shell-owned /wsn/settings state. Loaded once on mount and passed down to
	// ProfileTab as props so switching tabs doesn't re-fetch. ProfileTab calls
	// `refreshSettings` after a successful save to pull the freshly resolved
	// derivations (logo URL, hero URL, refund page label, etc.).
	const [ settings, setSettings ] = useState( null );
	const [ derivations, setDerivations ] = useState( {} );
	const [ sync, setSync ] = useState( null );
	const [ isLoadingSettings, setIsLoadingSettings ] = useState( true );
	const [ settingsError, setSettingsError ] = useState( null );

	// Returns the freshly-loaded payload so callers (e.g. the post-save
	// polling loop in ProfileTab) can read the new sync.last_synced without
	// waiting for a React re-render cycle.
	//
	// `options.silent = true` skips the isLoadingSettings flip so refreshes
	// fired AFTER a save (post-save polling, Retry button) don't unmount
	// the Profile tab into the loading state on every poll iteration —
	// each unmount-remount produced a visible flash every 2-16s during
	// the 30s polling window, with no resolution if AS was slow.
	const loadSettings = useCallback( async ( options = {} ) => {
		const silent = options?.silent === true;
		if ( ! silent ) {
			setIsLoadingSettings( true );
		}
		setSettingsError( null );
		try {
			const payload = await apiFetch( {
				path: '/wc/v3/payments/wsn/settings',
			} );
			setSettings( payload?.settings ?? {} );
			setDerivations( payload?.derivations ?? {} );
			setSync( payload?.sync ?? null );
			if ( ! silent ) {
				setIsLoadingSettings( false );
			}
			return payload;
		} catch ( e ) {
			setSettingsError( formatApiError( e ) );
			// Populate with safe empty defaults so ProfileTab's loading guard
			// releases and the error Notice can render with a Retry button.
			setSettings( {} );
			setDerivations( {} );
			setSync( null );
			if ( ! silent ) {
				setIsLoadingSettings( false );
			}
			return null;
		}
	}, [] );

	// Only fetch /wsn/settings once WSN is enabled. In the pre-enable state
	// the tab nav is hidden and ProfileTab never mounts, so the settings +
	// derivations payload is dead weight there. This also keeps the
	// pre-enable hero render fully synchronous (no async state updates after
	// the initial render).
	useEffect( () => {
		if ( isEnabled ) {
			loadSettings();
		}
	}, [ isEnabled, loadSettings ] );

	useEffect( () => {
		const handler = () => setCurrentTab( getInitialTabName() );
		window.addEventListener( 'hashchange', handler );
		return () => window.removeEventListener( 'hashchange', handler );
	}, [] );

	const onSelect = ( tabName ) => {
		setCurrentTab( tabName );
		if ( typeof window !== 'undefined' ) {
			window.location.hash = tabName;
		}
	};

	const handleEnabledChange = ( nextEnabled ) => {
		setIsEnabled( nextEnabled );
		// On disable, snap back to Overview so a future re-enable doesn't drop
		// the merchant on a hidden tab.
		if ( ! nextEnabled ) {
			setCurrentTab( 'overview' );
			if ( typeof window !== 'undefined' ) {
				window.location.hash = 'overview';
			}
		}
	};

	// Pre-enable: hide the tab nav entirely and render only the Overview tab's
	// PreEnableHero. The hero IS the page in this state.
	if ( ! isEnabled ) {
		return (
			<div className="wcpay-wsn-hub">
				<PageHeader />
				<div
					style={ {
						padding: `${ spacing.s4 } ${ spacing.s3 } 0`,
						background: colors.surface,
					} }
				>
					<OverviewTab
						isEnabled={ false }
						onEnabledChange={ handleEnabledChange }
					/>
				</div>
			</div>
		);
	}

	return (
		<div className="wcpay-wsn-hub">
			<PageHeader />
			<TabPanel
				// `initialTabName` is read on mount only; updates to
				// `currentTab` from hashchange (back/forward navigation)
				// would not take effect without forcing a remount. The
				// `key` pegs the component identity to `currentTab` so a
				// hash-driven update mounts a fresh TabPanel that picks
				// up the new initial tab. Mirrors the Reports page
				// pattern in this repo (`client/.../reports` uses the
				// same workaround).
				key={ currentTab }
				className="wcpay-wsn-hub__tabs"
				tabs={ TABS }
				initialTabName={ currentTab }
				onSelect={ onSelect }
			>
				{ ( tab ) => (
					// Wrap tab content in TabErrorBoundary so an uncaught
					// throw inside any single tab can't blank the entire hub.
					<TabErrorBoundary>
						<div
							style={ {
								padding: `${ spacing.s4 } ${ spacing.s3 } 0`,
								background: colors.surface,
							} }
						>
							{ tab.name === 'overview' && (
								<OverviewTab
									isEnabled={ true }
									onEnabledChange={ handleEnabledChange }
								/>
							) }
							{ tab.name === 'profile' && (
								<ProfileTab
									settings={ settings }
									derivations={ derivations }
									sync={ sync }
									isLoading={ isLoadingSettings }
									loadError={ settingsError }
									onRetry={ loadSettings }
									refreshSettings={ loadSettings }
								/>
							) }
						</div>
					</TabErrorBoundary>
				) }
			</TabPanel>
		</div>
	);
};

export default WsnHubApp;
