/**
 * ProfileSyncStatus — sync-state badge for the Profile tab.
 *
 * NOT the same as `profile-tab/sync-badge.js` (which is the THEME-branding
 * badge, block-vs-classic). This badge reports the Profile emitter's
 * push state — last successful push time, last error, and a manual
 * "Retry sync" button backed by POST /wc/v3/payments/wsn/profile-resync.
 *
 * Three visual states:
 *   - `success` (green)  : last_synced set, no error. Renders "Last synced X ago".
 *   - `failed`  (red)    : last_error set. Renders the error message + Retry.
 *   - `never`   (gray)   : last_synced null AND last_error null. Renders Retry.
 *
 * Plus a transient `syncing` state after a Retry click — disables the
 * button and shows "Syncing…". After `debounce_seconds + 2s` the parent
 * `refreshSettings` is called to pull the post-push state.
 *
 * Owned by RSM-3945 (sync-state UI wireup).
 *
 * @format
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';

import { colors, radii, spacing } from '../tokens';
import { formatApiError } from '../utils/format-api-error';

/**
 * Format a unix timestamp as a relative "X minutes ago" / "X hours ago"
 * string. WP's `@wordpress/date` lacks a relative-time helper, and we
 * don't want to pull moment.js for one line. Coarse buckets are fine —
 * the merchant just needs "recent vs. stale" precision, not seconds.
 *
 * @param {number} unixSeconds Unix timestamp of the event.
 * @return {string} Human-readable relative time.
 */
const formatRelativeTime = ( unixSeconds ) => {
	const now = Math.floor( Date.now() / 1000 );
	const delta = Math.max( 0, now - unixSeconds );

	if ( delta < 60 ) {
		return __( 'just now', 'woocommerce-payments' );
	}
	if ( delta < 3600 ) {
		const mins = Math.floor( delta / 60 );
		return sprintf(
			/* translators: %d: number of minutes */
			__( '%d min ago', 'woocommerce-payments' ),
			mins
		);
	}
	if ( delta < 86400 ) {
		const hours = Math.floor( delta / 3600 );
		return sprintf(
			/* translators: %d: number of hours */
			__( '%d hr ago', 'woocommerce-payments' ),
			hours
		);
	}
	const days = Math.floor( delta / 86400 );
	return sprintf(
		_n(
			/* translators: %d: number of days */
			'%d day ago',
			/* translators: %d: number of days */
			'%d days ago',
			days,
			'woocommerce-payments'
		),
		days
	);
};

/**
 * @param {Object}      props
 * @param {Object|null} props.sync                    Sync block from the /wsn/settings GET response.
 * @param {number|null} [props.sync.last_synced]      Unix ts of last successful push.
 * @param {Object|null} [props.sync.last_error]       { message, timestamp } or null.
 * @param {number}      [props.sync.debounce_seconds] Emitter debounce window (front-end uses for refresh timing).
 * @param {() => void}  props.onRefresh               Called after a successful Retry to re-fetch settings.
 * @param {boolean}     [props.isSyncing]             External "currently syncing" flag (e.g. the Save handler is
 *                                                    polling for last_synced to advance). Forces the badge into
 *                                                    the syncing state so the merchant sees activity instead of
 *                                                    a stale Last-synced timestamp.
 */
const ProfileSyncStatus = ( {
	sync,
	onRefresh,
	isSyncing: externalIsSyncing = false,
} ) => {
	const [ isRetrying, setIsRetrying ] = useState( false );
	const [ retryError, setRetryError ] = useState( null );

	// Track the post-Retry refresh timer so we can cancel it on unmount.
	// Without this, navigating away during the 62s wait fires setIsRetrying
	// on an unmounted component — React logs a dev-mode warning. Harmless
	// in production but the warning pollutes the dev console.
	const refreshTimerRef = useRef( null );

	useEffect( () => {
		return () => {
			if ( refreshTimerRef.current ) {
				window.clearTimeout( refreshTimerRef.current );
				refreshTimerRef.current = null;
			}
		};
	}, [] );

	const lastSynced = sync?.last_synced ?? null;
	const lastError = sync?.last_error ?? null;
	const debounceSeconds = sync?.debounce_seconds ?? 60;

	// Derived state. `syncing` takes precedence — even if the props still
	// show a stale error, the user just clicked Retry and the optimistic UI
	// should reflect "doing something now". `externalIsSyncing` is the
	// shell-driven equivalent (post-save polling); honored the same way.
	const computeState = () => {
		if ( isRetrying || externalIsSyncing ) {
			return 'syncing';
		}
		if ( lastError ) {
			return 'failed';
		}
		if ( lastSynced ) {
			return 'success';
		}
		return 'never';
	};
	const state = computeState();

	const handleRetry = useCallback( async () => {
		setIsRetrying( true );
		setRetryError( null );

		try {
			await apiFetch( {
				path: '/wc/v3/payments/wsn/profile-resync',
				method: 'POST',
			} );

			// The AS action fires `debounce_seconds` after the schedule.
			// Refreshing immediately would still show the stale state, so
			// wait the full debounce window + 2s buffer before pulling new
			// state. A more sophisticated polling approach is out of scope
			// for v1 — the merchant can manually reload if the wait feels
			// long. Timer id captured in ref so the unmount effect can
			// cancel it.
			refreshTimerRef.current = window.setTimeout( () => {
				refreshTimerRef.current = null;
				// Silent refresh so the Profile tab doesn't briefly
				// unmount into the loading state — the merchant clicked
				// Retry while looking at the static form and shouldn't
				// see a wholesale tab re-render.
				onRefresh?.( { silent: true } );
				setIsRetrying( false );
			}, debounceSeconds * 1000 + 2000 );
		} catch ( err ) {
			setRetryError( formatApiError( err ) );
			setIsRetrying( false );
		}
	}, [ debounceSeconds, onRefresh ] );

	const palette = {
		success: {
			text: colors.successText,
			bg: colors.successBg,
			border: colors.successBorder,
			dot: colors.successText,
		},
		failed: {
			text: colors.dangerText,
			bg: colors.dangerBg,
			border: colors.dangerText,
			dot: colors.dangerText,
		},
		never: {
			text: colors.textSecondary,
			bg: colors.surfaceAdmin,
			border: colors.borderSubtle,
			dot: colors.textMuted,
		},
		syncing: {
			text: colors.textSecondary,
			bg: colors.surfaceAdmin,
			border: colors.borderSubtle,
			dot: colors.textMuted,
		},
	}[ state ];

	let message;
	switch ( state ) {
		case 'success':
			message = sprintf(
				/* translators: %s: relative time like "5 min ago" */
				__( 'Last synced %s', 'woocommerce-payments' ),
				formatRelativeTime( lastSynced )
			);
			break;
		case 'failed':
			message = lastError?.message
				? sprintf(
						/* translators: %s: error message from the failed sync */
						__( 'Sync failed: %s', 'woocommerce-payments' ),
						lastError.message
				  )
				: __( 'Sync failed.', 'woocommerce-payments' );
			break;
		case 'never':
			message = __( 'Not yet synced.', 'woocommerce-payments' );
			break;
		case 'syncing':
		default:
			message = __( 'Syncing…', 'woocommerce-payments' );
			break;
	}

	const showRetry =
		state === 'failed' || state === 'never' || state === 'syncing';

	return (
		<div
			className="wcpay-wsn-profile-sync-status"
			data-state={ state }
			style={ {
				display: 'flex',
				alignItems: 'center',
				gap: spacing.s2,
				fontSize: '12px',
				color: palette.text,
				background: palette.bg,
				border: `1px solid ${ palette.border }`,
				borderRadius: radii.sm,
				padding: '6px 10px',
				marginBottom: spacing.s4,
			} }
			role="status"
		>
			<span
				aria-hidden="true"
				style={ {
					display: 'inline-block',
					width: '8px',
					height: '8px',
					borderRadius: radii.pill,
					background: palette.dot,
					flexShrink: 0,
				} }
			/>
			<span style={ { flexGrow: 1 } }>{ message }</span>
			{ showRetry && (
				<Button
					variant="link"
					onClick={ handleRetry }
					disabled={ isRetrying }
					style={ { padding: 0 } }
				>
					{ __( 'Retry sync', 'woocommerce-payments' ) }
				</Button>
			) }
			{ retryError && (
				<span
					role="alert"
					style={ {
						marginLeft: spacing.s2,
						color: colors.dangerText,
					} }
				>
					{ retryError }
				</span>
			) }
		</div>
	);
};

export default ProfileSyncStatus;
