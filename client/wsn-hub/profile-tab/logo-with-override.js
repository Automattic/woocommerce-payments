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
 * Owned by RSM-2481.
 *
 * @format
 */

import { MediaUpload, MediaUploadCheck } from '@wordpress/media-utils';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { colors, spacing, radii } from '../tokens';

/**
 * @param {Object}                 props              Component props.
 * @param {number|null}            props.overrideId   Override attachment ID; null when using site logo.
 * @param {string|null}            props.resolvedUrl  Server-resolved URL for the current state.
 * @param {'override'|'site_logo'} props.logoSource   Which source produced resolvedUrl.
 * @param {Function}               props.onChange     Called with the new attachment ID (or null to reset).
 */
const LogoWithOverride = ( {
	overrideId,
	resolvedUrl,
	logoSource,
	onChange,
} ) => {
	const hasOverride = overrideId !== null && overrideId !== undefined;
	const usingSiteLogo = logoSource === 'site_logo' && resolvedUrl;
	const hasNothing = ! resolvedUrl;

	return (
		<div
			style={ {
				display: 'flex',
				alignItems: 'flex-start',
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

			<div style={ { paddingTop: '4px', flex: 1, minWidth: 0 } }>
				{ /* Source attribution dot + copy. Only shown when site logo
				     is the active source — when override is set, the
				     attribution is implicit ("you uploaded this"). */ }
				{ usingSiteLogo && (
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
								background: colors.successText,
								flexShrink: 0,
							} }
						/>
						{ __(
							'Using your site logo — pulled from Site Identity',
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
						gap: spacing.s3,
						alignItems: 'center',
					} }
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								onChange( media?.id ?? null )
							}
							allowedTypes={ [ 'image' ] }
							value={ overrideId ?? undefined }
							render={ ( { open } ) => (
								<Button
									variant="link"
									onClick={ open }
									style={ { fontSize: '12px', padding: 0 } }
								>
									{ hasOverride
										? __(
												'Choose a different image…',
												'woocommerce-payments'
										  )
										: __(
												'Replace with custom logo…',
												'woocommerce-payments'
										  ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>

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
								onClick={ () => onChange( null ) }
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
