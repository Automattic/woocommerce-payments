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
 * @param {Object} all Full settings blob.
 * @return {Object} Subset containing only the editable Profile keys.
 */
const pickProfileFields = ( all ) => {
	const out = {};
	for ( const key of PROFILE_KEYS ) {
		out[ key ] = all?.[ key ] ?? null;
	}
	return out;
};

/**
 * Deep-equal the two profile-field objects for dirty tracking.
 * Values are all scalar (int/string/null) so per-key comparison suffices.
 *
 * @param {Object} a Local edits.
 * @param {Object} b Last-saved snapshot.
 * @return {boolean} True when the two are equivalent (no unsaved edits).
 */
const profilesEqual = ( a, b ) => {
	for ( const key of PROFILE_KEYS ) {
		if ( ( a?.[ key ] ?? null ) !== ( b?.[ key ] ?? null ) ) {
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

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wc/v3/payments/wsn/settings' } )
			.then( ( payload ) => {
				if ( cancelled ) return;
				const profile = pickProfileFields( payload?.settings ?? {} );
				setLocalSettings( profile );
				setSavedSettings( profile );
				setDerivations( payload?.derivations ?? {} );
				setIsLoading( false );
			} )
			.catch( () => {
				if ( cancelled ) return;
				setIsLoading( false );
				setSaveNotice( {
					status: 'error',
					message: __(
						'Could not load Profile settings. Try refreshing the page.',
						'woocommerce-payments'
					),
				} );
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const isDirty =
		localSettings !== null &&
		savedSettings !== null &&
		! profilesEqual( localSettings, savedSettings );

	const handleChange = ( { key, value } ) => {
		setLocalSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaveNotice( null );
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
			setSaveNotice( {
				status: 'success',
				message: __( 'Profile saved.', 'woocommerce-payments' ),
			} );
		} catch ( e ) {
			setSaveNotice( {
				status: 'error',
				message:
					e?.message ??
					__(
						'Could not save Profile settings. Please try again.',
						'woocommerce-payments'
					),
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
					</Notice>
				</div>
			) }

			<BrandingCard
				settings={ localSettings }
				derivations={ derivations }
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
