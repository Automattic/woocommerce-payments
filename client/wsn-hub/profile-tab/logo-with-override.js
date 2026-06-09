/**
 * LogoWithOverride — two-state logo control.
 *
 *   - **Default** (`logo_override_id` is null): renders the resolved site logo
 *     from Site Identity. Source-dot + "Using your site logo" copy. The
 *     "Replace with custom logo" affordance opens the Media Library picker.
 *
 *   - **Override** (`logo_override_id` is set): renders the override image.
 *     A "Reset to site logo" link clears the override (null-out the ID;
 *     composer falls back to get_theme_mod('custom_logo')).
 *
 * The MediaUpload component is from @wordpress/media-utils — same primitive
 * WC admin uses for media pickers. We store ONLY the attachment ID; the URL
 * is resolved server-side by the settings GET derivations.
 *
 * The MediaUploadCheck capability gate (`upload_files`) is omitted because
 * this page is already gated to `manage_woocommerce`, and importing
 * MediaUploadCheck from @wordpress/media-utils throws at runtime (it only
 * lives in @wordpress/block-editor; the named export is undefined here).
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { MediaUpload } from '@wordpress/media-utils';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { colors, spacing, radii } from '../tokens';

/**
 * Belt-and-suspenders guard around MediaUpload's `open` callback.
 *
 * The load-time fix is calling wp_enqueue_media() server-side from
 * WSN_Hub so wp.media is always present when this component renders.
 * This wrapper is defensive: if a future change ever breaks the
 * enqueue order (e.g., script handle renamed, dependency dropped,
 * page hook changed), invoking open() would throw and take down the
 * whole tab. We swallow the error, log a diagnostic that points at
 * the actual root cause, and leave the UI usable.
 *
 * @param {Function} openFn The `open` callback from MediaUpload's render prop.
 * @return {Function} An onClick handler safe to attach to a button.
 */
const safeOpenMediaModal = ( openFn ) => () => {
	if (
		typeof window?.wp?.media !== 'function' ||
		! window?.wp?.media?.view
	) {
		// eslint-disable-next-line no-console -- intentional diagnostic
		console.error(
			'[WSN] wp.media is not loaded; check wp_enqueue_media() in WSN_Hub'
		);
		return;
	}
	try {
		openFn();
	} catch ( err ) {
		// eslint-disable-next-line no-console -- intentional diagnostic
		console.error( '[WSN] MediaUpload open() threw:', err );
	}
};

/**
 * @param {Object}                 props             Component props.
 * @param {number|null}            props.overrideId  Override attachment ID; null when using site logo.
 * @param {string|null}            props.resolvedUrl Server-resolved URL for the current state.
 * @param {'override'|'site_logo'} props.logoSource  Which source produced resolvedUrl.
 * @param {Function}               props.onChange    Called with the new attachment ID (or null to reset).
 */
const LogoWithOverride = ( {
	overrideId,
	resolvedUrl,
	logoSource,
	onChange,
} ) => {
	const hasOverride = overrideId !== null && overrideId !== undefined;
	const usingSiteLogo = logoSource === 'site_logo' && resolvedUrl;
	const usingSiteIcon = logoSource === 'site_icon' && resolvedUrl;
	const hasNothing = ! resolvedUrl;

	return (
		<div
			style={ {
				display: 'flex',
				alignItems: 'flex-start',
				flexWrap: 'wrap',
				gap: spacing.s5,
				marginBottom: spacing.s5,
			} }
		>
			{ /* Image preview box — solid border when filled, dashed when empty. */ }
			<div
				style={ {
					width: '100px',
					height: '100px',
					border: hasNothing
						? `2px dashed ${ colors.borderStrong }`
						: `1px solid ${ colors.borderSubtle }`,
					borderRadius: radii.md,
					background: hasNothing
						? colors.surfaceAdmin
						: colors.surface,
					padding: hasNothing ? 0 : '8px',
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					flexShrink: 0,
				} }
			>
				{ resolvedUrl ? (
					<img
						src={ resolvedUrl }
						alt=""
						style={ {
							maxWidth: '100%',
							maxHeight: '100%',
							objectFit: 'contain',
						} }
					/>
				) : (
					<span
						style={ {
							fontSize: '11px',
							color: colors.textMuted,
							textAlign: 'center',
							lineHeight: 1.4,
						} }
					>
						{ __( 'No logo', 'woocommerce-payments' ) }
					</span>
				) }
			</div>

			{ /*
				Copy column sits next to the 100px logo on desktop;
				wraps to a new row UNDER the logo on narrow viewports
				when it can't fit at least 220px (single-word-per-line
				breakpoint for the description copy). The `flex: 1` /
				`minWidth: '220px'` pair keeps it greedy on desktop
				and triggers the wrap on mobile.
			*/ }
			<div
				style={ {
					paddingTop: '4px',
					flex: '1 1 220px',
					minWidth: '220px',
				} }
			>
				{ /* Source attribution dot + copy. Only shown when a default
				     (site logo or site icon) is the active source — when
				     override is set, the attribution is implicit
				     ("you uploaded this"). Copy varies by source so the
				     merchant knows what they're seeing: a proper site
				     logo is a normal state; falling back to the site icon
				     (favicon) means they should set a real logo. */ }
				{ ( usingSiteLogo || usingSiteIcon ) && (
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: spacing.s2,
							marginBottom: spacing.s2,
							fontSize: '12px',
							color: colors.textSecondary,
						} }
					>
						<span
							aria-hidden="true"
							style={ {
								width: '6px',
								height: '6px',
								borderRadius: '50%',
								background: usingSiteLogo
									? colors.successText
									: colors.textMuted,
								flexShrink: 0,
							} }
						/>
						{ usingSiteLogo
							? __(
									'Using your site logo — pulled from Site Identity',
									'woocommerce-payments'
							  )
							: __(
									'Using your site icon — set a proper site logo for better quality',
									'woocommerce-payments'
							  ) }
					</div>
				) }

				<p
					style={ {
						fontSize: '12px',
						color: colors.textSecondary,
						marginBottom: spacing.s2,
						lineHeight: 1.5,
					} }
				>
					{ __(
						'This logo appears on your Shopping Network storefront and in ' +
							'search results. Upload a different image here to override ' +
							'it on the network without changing your site logo.',
						'woocommerce-payments'
					) }
				</p>

				<div
					style={ {
						display: 'flex',
						flexWrap: 'wrap',
						gap: `${ spacing.s2 } ${ spacing.s3 }`,
						alignItems: 'center',
					} }
				>
					<MediaUpload
						onSelect={ ( media ) =>
							onChange(
								media?.id ?? null,
								// Forward `media.url` (NOT sizes.full.url)
								// so the optimistic preview matches the
								// URL the server will return from
								// wp_get_attachment_url() on the next
								// fetch. For images >2560px WP uploads
								// a scaled version — media.url returns
								// the scaled URL, sizes.full.url returns
								// the original. Picking one and the
								// server returning the other makes the
								// preview shift visibly when save-success
								// replaces the overlay.
								media?.url ?? null
							)
						}
						allowedTypes={ [ 'image' ] }
						value={ overrideId ?? undefined }
						render={ ( { open } ) => (
							<Button
								variant="link"
								onClick={ safeOpenMediaModal( open ) }
								style={ { fontSize: '12px', padding: 0 } }
							>
								{ hasOverride
									? __( 'Replace…', 'woocommerce-payments' )
									: __(
											'Upload custom logo…',
											'woocommerce-payments'
									  ) }
							</Button>
						) }
					/>

					{ hasOverride && (
						<>
							<span
								aria-hidden="true"
								style={ { color: colors.borderStrong } }
							>
								|
							</span>
							<Button
								variant="link"
								onClick={ () => onChange( null, null ) }
								style={ {
									fontSize: '12px',
									padding: 0,
									color: colors.textSecondary,
								} }
							>
								{ __(
									'Reset to site logo',
									'woocommerce-payments'
								) }
							</Button>
						</>
					) }
				</div>
			</div>
		</div>
	);
};

export default LogoWithOverride;
