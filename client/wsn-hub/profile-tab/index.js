/**
 * ProfileTab — orchestrator for the Profile tab.
 *
 * Mounts: fetches GET /wc/v3/payments/wsn/settings to get both the current
 * settings AND the resolved derivations the BrandingCard + ContactPoliciesCard
 * need (logo URL, hero URL, shop name, tagline, shipping regions, free shipping
 * summary, refund page label, theme type).
 *
 * Edits live in `localSettings`. The Save button is disabled when nothing
 * differs from `savedSettings`. On save, sends the changed keys via PUT and
 * resyncs both state slots from the response (so derivations stay current
 * even though the server might have rejected individual fields with a 422).
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';

import BrandingCard from './branding-card';
import ContactPoliciesCard from './contact-policies-card';
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

const ProfileTab = () => {
	const [ localSettings, setLocalSettings ] = useState( null );
	const [ savedSettings, setSavedSettings ] = useState( null );
	const [ derivations, setDerivations ] = useState( {} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveNotice, setSaveNotice ] = useState( null );

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

	const loadProfile = () => {
		setIsLoading( true );
		setSaveNotice( null );
		return apiFetch( { path: '/wc/v3/payments/wsn/settings' } )
			.then( ( payload ) => {
				const profile = pickProfileFields( payload?.settings ?? {} );
				setLocalSettings( profile );
				setSavedSettings( profile );
				setDerivations( payload?.derivations ?? {} );
				setIsLoading( false );
			} )
			.catch( () => {
				// Populate localSettings + derivations with safe empty
				// defaults so the early-return loading guard releases and
				// the Notice (with Retry) can render. Without this, the
				// `! localSettings` guard wins forever and the page is
				// stuck at "Loading Profile…".
				setLocalSettings( pickProfileFields( {} ) );
				setDerivations( {} );
				setIsLoading( false );
				setSaveNotice( {
					status: 'error',
					message: __(
						'Could not load Profile settings. Try refreshing the page.',
						'woocommerce-payments'
					),
				} );
			} );
	};

	useEffect( () => {
		loadProfile();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

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

	const handleSave = async () => {
		if ( ! localSettings || ! isDirty ) return;
		setIsSaving( true );
		setSaveNotice( null );
		try {
			const payload = await apiFetch( {
				path: '/wc/v3/payments/wsn/settings',
				method: 'PUT',
				data: localSettings,
			} );
			const profile = pickProfileFields( payload?.settings ?? {} );
			setSavedSettings( profile );
			setLocalSettings( profile );
			setDerivations( payload?.derivations ?? {} );
			// Server-resolved derivation URLs now authoritative — drop the
			// transient pick-time URLs so we read the canonical resolved
			// values (handles WP regenerating thumbnails, CDN rewrites,
			// etc. that may differ from what MediaUpload reported).
			setPendingMediaUrls( {} );
			setSaveNotice( {
				status: 'success',
				message: __( 'Profile saved.', 'woocommerce-payments' ),
			} );
		} catch ( e ) {
			setSaveNotice( {
				status: 'error',
				message: formatApiError( e ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	if ( isLoading || ! localSettings ) {
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
		<div style={ { maxWidth: '700px' } }>
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
						{ saveNotice.status === 'error' &&
							savedSettings === null && (
								<div style={ { marginTop: spacing.s2 } }>
									<Button
										variant="secondary"
										onClick={ loadProfile }
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
			) }

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
