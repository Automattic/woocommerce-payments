/**
 * ProfileTab — orchestrator for the Profile tab.
 *
 * Receives settings + derivations as props from the WsnHubApp shell, which
 * fetches GET /wc/v3/payments/wsn/settings once on its own mount. ProfileTab
 * no longer fetches on tab-visit, so switching between Overview and Profile
 * doesn't re-hit the endpoint.
 *
 * Edits live in `localSettings` (a dirty buffer). `savedSettings` is derived
 * from `props.settings`. The Save button is disabled when nothing differs
 * from `savedSettings`. On save, sends the changed keys via PUT and then
 * calls `props.refreshSettings()` so the shell re-reads settings AND
 * derivations from the server (derivations may have shifted even if the
 * server rejected individual fields with a 422).
 *
 * When `props.settings` changes reference (after refreshSettings completes,
 * or if the parent ever swaps the underlying payload), the localSettings
 * buffer is reset via a useEffect so the UI reflects the new server state.
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';

import BrandingCard from './branding-card';
import ContactPoliciesCard from './contact-policies-card';
import ProfileSyncStatus from './profile-sync-status';
import { colors, spacing } from '../tokens';
import { formatApiError } from '../utils/format-api-error';

/**
 * Editable Profile-tab keys. Visibility keys + `enabled` are intentionally
 * excluded — they're owned by the Visibility tab + the enable/disable
 * affordance on the Overview tab.
 */
const PROFILE_KEYS = [
	'hero_image_id',
	'logo_override_id',
	'contact_email',
	'refund_page_id',
];

/**
 * Pick only the Profile-relevant keys from a settings blob.
 *
 * `contact_email` is THREE-STATE (null = use WC default, '' = explicit
 * empty, string = explicit override) — collapsing undefined→null is fine
 * (a missing payload key reads as "unset") but we must NOT collapse '' to
 * null, or the "explicit empty" state would be indistinguishable from
 * "unset" and the dirty tracker would think a save just happened restored
 * the merchant's intent.
 *
 * @param {Object} all Full settings blob.
 * @return {Object} Subset containing only the editable Profile keys.
 */
const pickProfileFields = ( all ) => {
	const out = {};
	for ( const key of PROFILE_KEYS ) {
		const v = all?.[ key ];
		out[ key ] = v === undefined ? null : v;
	}
	return out;
};

/**
 * Strict-equality comparison for dirty tracking. Uses Object.is so the
 * three-state contact_email distinction is preserved (`'' !== null`).
 *
 * @param {Object} a Local edits.
 * @param {Object} b Last-saved snapshot.
 * @return {boolean} True when the two are equivalent (no unsaved edits).
 */
const profilesEqual = ( a, b ) => {
	for ( const key of PROFILE_KEYS ) {
		const av = a?.[ key ] === undefined ? null : a[ key ];
		const bv = b?.[ key ] === undefined ? null : b[ key ];
		if ( ! Object.is( av, bv ) ) {
			return false;
		}
	}
	return true;
};

const ProfileTab = ( {
	settings,
	derivations = {},
	sync = null,
	isLoading = false,
	loadError = null,
	onRetry,
	refreshSettings,
} ) => {
	// `savedSettings` is DERIVED from props (not state) — the shell owns the
	// authoritative settings blob, ProfileTab just narrows it to the
	// Profile-relevant fields for dirty tracking.
	const savedSettings =
		settings !== null && settings !== undefined
			? pickProfileFields( settings )
			: null;

	// `localSettings` is the dirty buffer. Initialized from props on first
	// render, then reset via a useEffect whenever `props.settings` changes
	// reference (e.g. after refreshSettings() returns a fresh payload). The
	// initializer form avoids a "loading flash" of `null` between mount and
	// the first sync effect.
	const [ localSettings, setLocalSettings ] = useState( () =>
		settings !== null && settings !== undefined
			? pickProfileFields( settings )
			: null
	);

	// Sync localSettings whenever the shell hands us a new settings reference
	// (post-save refresh, retry-after-error, etc.). The shell is canonical;
	// the local buffer just shadows it while edits are in flight.
	//
	// Guard against clobbering an in-flight dirty buffer: only update
	// localSettings when the picked values from props actually differ from
	// what we have locally. Without this, the post-save polling loop —
	// which fires `refreshSettings()` up to 5 times per save — would reset
	// the merchant's typing on each poll iteration when sync is stalled
	// (WP cron not firing, AS slow). It also prevents an infinite
	// effect→render→effect ladder when pickProfileFields produces a new
	// object reference each call.
	useEffect( () => {
		if ( settings === null || settings === undefined ) {
			return;
		}
		const next = pickProfileFields( settings );
		setLocalSettings( ( prev ) =>
			prev !== null && profilesEqual( prev, next ) ? prev : next
		);
	}, [ settings ] );

	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveNotice, setSaveNotice ] = useState( null );

	// True while the post-save loop is polling /wsn/settings waiting for
	// `sync.last_synced` to advance. Drives the ProfileSyncStatus badge
	// into its "Syncing…" state so the merchant sees activity instead of
	// a stale timestamp during the AS-tick window.
	const [ isPostSaveSyncing, setIsPostSaveSyncing ] = useState( false );

	// Generation token bumped at the start of every handleSave invocation.
	// The polling loop reads its captured value back through this ref after
	// every await; if the current ref doesn't match, the loop is from a
	// superseded save and bails without touching shared state. Without
	// this, a second Save click during the first save's 30s polling window
	// produces stale setIsSaving(false) / setSaveNotice writes from the
	// first save that clobber the second save's UI state — the form
	// appears to "hang" while the in-flight second save can't visibly
	// progress.
	const saveGenerationRef = useRef( 0 );

	// Transient URLs captured at MediaUpload-pick time so the preview
	// updates IMMEDIATELY, before the save+GET cycle replaces them with the
	// server-resolved derivations.logo_url / .hero_image_url. Without these
	// the picker would store the new attachment ID locally but keep
	// rendering the OLD image URL (or none) until save — which reads as
	// "the picker didn't work".
	//
	// Keys mirror the PROFILE_KEYS attachment fields (logo_override_id,
	// hero_image_id). Cleared on save-success because derivations then
	// authoritatively reflects the saved IDs.
	const [ pendingMediaUrls, setPendingMediaUrls ] = useState( {} );

	const isDirty =
		localSettings !== null &&
		savedSettings !== null &&
		! profilesEqual( localSettings, savedSettings );

	const handleChange = ( { key, value, previewUrl } ) => {
		setLocalSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaveNotice( null );

		// Media pickers pass `previewUrl` so the UI can show the new image
		// without a save round-trip. A null value (Reset/Remove) clears
		// the override so the preview falls back to the next-best source.
		if ( previewUrl !== undefined ) {
			setPendingMediaUrls( ( prev ) => ( {
				...prev,
				[ key ]: value === null ? null : previewUrl,
			} ) );
		}
	};

	// Backoff schedule for the post-save sync poll, in milliseconds. Total
	// upper bound: 2 + 4 + 8 + 16 = 30s. Chosen to cover the realistic AS
	// tick window in dev (sub-second to a few seconds with WP cron enabled)
	// AND the worst case (WP cron disabled, server cron running every 60s
	// — though merchants in that posture will see the badge resolve on the
	// next manual refresh / tab switch). Geometric backoff lands on the
	// truth fast when AS is responsive and doesn't spam the endpoint when
	// it isn't.
	const POST_SAVE_POLL_DELAYS_MS = [ 2000, 4000, 8000, 16000 ];

	const handleSave = async () => {
		if ( ! localSettings || ! isDirty ) return;
		// Concurrency guard: refuse to start a second save while the
		// in-flight PUT itself is still going. We DO allow a new save
		// while a prior save's WooPay polling is still running — the
		// generation token below handles supersession of that polling
		// without blocking new edits.
		if ( isSaving ) return;

		// Bump the generation token so the previous save's polling loop
		// (if still alive) can detect supersession on its next iteration
		// and bail without touching shared state.
		saveGenerationRef.current += 1;
		const myGeneration = saveGenerationRef.current;
		const isStillCurrent = () => saveGenerationRef.current === myGeneration;

		setIsSaving( true );
		setSaveNotice( null );

		// Capture the pre-save sync timestamp so the poll below can detect
		// when the emitter writes a NEW one. Null is a valid baseline (a
		// store that has never synced — any non-null value below advances).
		const previousLastSynced = sync?.last_synced ?? null;

		// PUT first. Save button UI tracks ONLY this round-trip — not the
		// downstream WooPay-side sync. The two events are conceptually
		// distinct:
		//   - PUT success = "your changes are persisted on this store"
		//   - sync.last_synced advance = "WooPay received the new state"
		// Coupling them meant the Save button sat in 'Saving…' for the
		// full 30s polling window even after the PUT had already
		// returned 200. The Syncing… badge handles the WooPay-side
		// communication on its own; we don't need to layer a duplicate
		// indicator onto the Save button.
		try {
			await apiFetch( {
				path: '/wc/v3/payments/wsn/settings',
				method: 'PUT',
				data: localSettings,
			} );
		} catch ( e ) {
			if ( isStillCurrent() ) {
				setSaveNotice( {
					status: 'error',
					message: formatApiError( e ),
				} );
				setIsSaving( false );
			}
			return;
		}

		// If a newer Save has started while our PUT was in flight,
		// the newer flow now owns the post-save UI state — back out
		// silently rather than racing the newer save's setIsSaving /
		// setSaveNotice writes.
		if ( ! isStillCurrent() ) {
			return;
		}

		// PUT succeeded — release the Save button. Anything after this
		// point is the WooPay-sync follow-up, which is surfaced via the
		// badge, not the button.
		setIsSaving( false );
		setSaveNotice( {
			status: 'success',
			message: __( 'Profile saved.', 'woocommerce-payments' ),
		} );

		// Fire-and-forget the WooPay-sync follow-up. The polling loop
		// drives isPostSaveSyncing (badge state) and refreshes
		// derivations so the form reflects server-resolved values
		// (logo URL, hero URL, refund-page label, etc.). Errors here
		// are swallowed — the Profile WAS saved; merchant doesn't need
		// to see a "sync failed" toast on the Save button. The badge
		// surfaces sync failures directly.
		pollForWooPaySync( {
			previousLastSynced,
			isStillCurrent,
		} ).catch( () => {} );
	};

	// Extracted polling helper so handleSave can fire-and-forget without
	// awaiting it. The merchant's perception of "save" completes when
	// the PUT lands; this loop's job is to keep the badge honest.
	const pollForWooPaySync = async ( {
		previousLastSynced,
		isStillCurrent,
	} ) => {
		if ( typeof refreshSettings !== 'function' ) {
			return;
		}

		const advanced = ( payload ) => {
			const next = payload?.sync?.last_synced ?? null;
			return next !== null && next !== previousLastSynced;
		};

		// First refresh runs without a delay — derivations have changed
		// (logo URL, hero URL, refund-page label may have shifted) and
		// the form should reflect the server's resolved values before
		// we start polling for the badge.
		let latest = await refreshSettings( { silent: true } );
		if ( ! isStillCurrent() ) {
			return;
		}

		// Pending media overlays only matter until the server hands us
		// fresh derivations — by now it has, so drop them.
		setPendingMediaUrls( {} );

		if ( advanced( latest ) ) {
			return;
		}

		// AS hasn't ticked yet — surface the in-flight sync via the badge
		// and poll on a geometric backoff. Each iteration short-circuits
		// when a newer save supersedes this one or when sync advances.
		setIsPostSaveSyncing( true );
		let resolvedDuringPolling = false;
		try {
			for ( const delayMs of POST_SAVE_POLL_DELAYS_MS ) {
				// eslint-disable-next-line no-await-in-loop, no-loop-func -- intentional polling
				await new Promise( ( resolve ) =>
					window.setTimeout( resolve, delayMs )
				);
				if ( ! isStillCurrent() ) {
					return;
				}
				// eslint-disable-next-line no-await-in-loop -- intentional polling
				latest = await refreshSettings( { silent: true } );
				if ( ! isStillCurrent() ) {
					return;
				}
				if ( advanced( latest ) ) {
					resolvedDuringPolling = true;
					return;
				}
			}
		} finally {
			if ( isStillCurrent() ) {
				setIsPostSaveSyncing( false );
			}
		}

		// Tail refresh — polling exhausted without seeing the AS tick.
		// WP cron may still tick AS in the next minute (especially in
		// dev environments where cron fires sporadically); without
		// this, sync.last_synced advances on the server but the Hub's
		// `sync` prop stays stale until a page refresh, and the badge
		// reads "Syncing…" indefinitely from the merchant's
		// perspective. One more silent refresh 60s later picks up the
		// late tick without an infinite poll. Skipped when polling
		// resolved cleanly (no need) or the save was superseded.
		if ( resolvedDuringPolling ) {
			return;
		}
		window.setTimeout( () => {
			if ( ! isStillCurrent() ) {
				return;
			}
			refreshSettings( { silent: true } ).catch( () => {} );
		}, 60_000 );
	};

	if ( isLoading || ! localSettings ) {
		// Surface the shell-level load error with a Retry button. The early
		// return on `! localSettings` also handles the case where the shell
		// hasn't yet supplied a settings payload (initial mount).
		if ( loadError ) {
			return (
				<div style={ { padding: spacing.s5 } }>
					<Notice status="error" isDismissible={ false }>
						{ __(
							'Could not load Profile settings. Try refreshing the page.',
							'woocommerce-payments'
						) }
						{ typeof onRetry === 'function' && (
							<div style={ { marginTop: spacing.s2 } }>
								<Button
									variant="secondary"
									onClick={ onRetry }
									disabled={ isLoading }
								>
									{ __(
										'Try again',
										'woocommerce-payments'
									) }
								</Button>
							</div>
						) }
					</Notice>
				</div>
			);
		}
		return (
			<div
				style={ {
					padding: spacing.s5,
					color: colors.textMuted,
					fontStyle: 'italic',
				} }
			>
				{ __( 'Loading Profile…', 'woocommerce-payments' ) }
			</div>
		);
	}

	return (
		// minWidth: 0 lets the wrapper shrink below its intrinsic
		// min-content size (long word in a Picker or readonly textarea
		// would otherwise force horizontal overflow on phones).
		<div style={ { maxWidth: '700px', minWidth: 0 } }>
			<h2
				style={ {
					fontSize: '18px',
					fontWeight: 600,
					lineHeight: 1.3,
					color: colors.textPrimary,
					margin: `0 0 ${ spacing.s1 }`,
				} }
			>
				{ __( 'Storefront Profile', 'woocommerce-payments' ) }
			</h2>
			<p
				style={ {
					fontSize: '13px',
					color: colors.textSecondary,
					fontStyle: 'italic',
					marginBottom: spacing.s6,
					lineHeight: 1.5,
				} }
			>
				{ __(
					'How your store appears in the Woo Shopping Network. Most fields sync automatically from your WooCommerce settings.',
					'woocommerce-payments'
				) }
			</p>

			{ saveNotice && (
				<div style={ { marginBottom: spacing.s4 } }>
					<Notice
						status={ saveNotice.status }
						onRemove={ () => setSaveNotice( null ) }
						isDismissible
					>
						{ saveNotice.message }
					</Notice>
				</div>
			) }

			<ProfileSyncStatus
				sync={ sync }
				onRefresh={ refreshSettings }
				isSyncing={ isPostSaveSyncing }
			/>

			<BrandingCard
				settings={ localSettings }
				derivations={ {
					...derivations,
					// Overlay any just-picked URLs so previews update before
					// save. The semantics are subtle because RESET (a null
					// pending value) must mean different things per field:
					//
					//   - Logo Reset → fall back to default_logo_url (the
					//     server-resolved next-best fallback — site_logo
					//     if set, else site_icon, else null). Showing
					//     null/"No logo" here regresses the pre-optimistic
					//     behavior where Reset returned to the default on
					//     next save.
					//   - Hero Remove → there is no fallback, the hero
					//     just disappears. Null IS the intended preview.
					//
					// Non-null pending values are the just-picked URLs and
					// always win; this branch only matters for the null
					// (clear-the-override) case.
					logo_url:
						'logo_override_id' in pendingMediaUrls
							? pendingMediaUrls.logo_override_id ??
							  derivations.default_logo_url ??
							  null
							: derivations.logo_url,
					hero_image_url:
						'hero_image_id' in pendingMediaUrls
							? pendingMediaUrls.hero_image_id
							: derivations.hero_image_url,
					// logo_source must reflect LOCAL state. When the merchant
					// just picked an override, derivations.logo_source from
					// the load-time GET is stale and would render
					// "Using your site logo" copy next to the override
					// image. Recompute:
					//   - override set → 'override'
					//   - no override → server's resolved default source
					//     ('site_logo' | 'site_icon' | 'none')
					logo_source:
						localSettings?.logo_override_id !== null &&
						localSettings?.logo_override_id !== undefined
							? 'override'
							: derivations.default_logo_source ?? 'none',
				} }
				onChange={ handleChange }
			/>

			<ContactPoliciesCard
				settings={ localSettings }
				derivations={ derivations }
				onChange={ handleChange }
			/>

			<div
				style={ {
					display: 'flex',
					justifyContent: 'flex-end',
					gap: spacing.s2,
					paddingTop: spacing.s4,
					borderTop: `1px solid ${ colors.borderSubtle }`,
				} }
			>
				<Button
					variant="primary"
					onClick={ handleSave }
					disabled={ ! isDirty || isSaving }
					isBusy={ isSaving }
				>
					{ isSaving
						? __( 'Saving…', 'woocommerce-payments' )
						: __( 'Save changes', 'woocommerce-payments' ) }
				</Button>
			</div>
		</div>
	);
};

export default ProfileTab;
