/**
 * HeroBannerPicker — wide banner asset shown atop the merchant's storefront
 * page on pay.woo.com. Recommended dimensions 1440×420 (~3.43:1 aspect).
 *
 * Empty state: dashed upload zone matching the mockup, with "Click to
 * upload" + Media Library link. Aspect-ratio preview
 * shows the actual banner shape so the merchant understands the surface
 * they're filling before selecting.
 *
 * Filled state: the chosen image fills the same aspect-ratio box, with a
 * "Replace" / "Remove" affordance below.
 *
 * MediaUploadCheck (the `upload_files` capability gate) is intentionally
 * omitted — the admin page is already gated to `manage_woocommerce`, and
 * MediaUploadCheck is NOT exported by the media-utils package (only by
 * the block-editor package), so importing it here throws at runtime.
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
 * @param {Object}      props
 * @param {number|null} props.attachmentId Current hero attachment ID.
 * @param {string|null} props.resolvedUrl  Server-resolved URL for the attachment, or null.
 * @param {Function}    props.onChange     Called with the new attachment ID (or null to remove).
 */
const HeroBannerPicker = ( { attachmentId, resolvedUrl, onChange } ) => {
	const hasImage =
		attachmentId !== null && attachmentId !== undefined && resolvedUrl;

	return (
		<div style={ { marginBottom: spacing.s5 } }>
			<div
				style={ {
					display: 'flex',
					alignItems: 'baseline',
					justifyContent: 'space-between',
					gap: spacing.s3,
					marginBottom: spacing.s2,
				} }
			>
				{ /* Display-only label for the dropzone — the dropzone itself
				     is a <button> with aria-label, so this is not a form
				     control label in the traditional sense. */ }
				<span
					style={ {
						fontSize: '11px',
						fontWeight: 600,
						textTransform: 'uppercase',
						letterSpacing: '0.04em',
						color: colors.textMuted,
					} }
				>
					{ __( 'Hero banner', 'woocommerce-payments' ) }
				</span>
				<span
					style={ {
						fontSize: '11px',
						color: colors.textMuted,
					} }
				>
					{ __(
						'Recommended 1440 × 420 · JPG or PNG · max 2 MB',
						'woocommerce-payments'
					) }
				</span>
			</div>

			<MediaUpload
				onSelect={ ( media ) =>
					onChange(
						media?.id ?? null,
						// `media.url` matches what the server returns
						// from wp_get_attachment_url() on the next
						// fetch — using sizes.full.url instead causes
						// large images to swap to a different URL on
						// save (full vs. -scaled), which reads as the
						// preview "resizing" or "disappearing". See
						// logo-with-override.js for the longer note.
						media?.url ?? null
					)
				}
				allowedTypes={ [ 'image' ] }
				value={ attachmentId ?? undefined }
				render={ ( { open } ) => (
					<>
						<button
							type="button"
							onClick={ safeOpenMediaModal( open ) }
							style={ {
								width: '100%',
								// Always preserve the 1440:420 ratio at
								// the current column width — the preview
								// reads as a scaled-down version of the
								// hero on pay.woo.com. No maxHeight cap;
								// merchants explicitly asked for the
								// proportional preview so they can judge
								// composition.
								aspectRatio: '1440 / 420',
								border: hasImage
									? `1px solid ${ colors.borderSubtle }`
									: `2px dashed ${ colors.borderStrong }`,
								borderRadius: radii.md,
								background: hasImage
									? colors.surface
									: `linear-gradient(135deg, rgba(114, 14, 236, 0.04), ` +
									  `rgba(255, 255, 255, 0) 60%), ${ colors.surfaceAdmin }`,
								backgroundImage: hasImage
									? `url("${ resolvedUrl }")`
									: undefined,
								backgroundSize: 'cover',
								backgroundPosition: 'center',
								display: hasImage ? 'block' : 'flex',
								flexDirection: 'column',
								alignItems: 'center',
								justifyContent: 'center',
								gap: spacing.s2,
								cursor: 'pointer',
								color: colors.textMuted,
								textAlign: 'center',
								padding: hasImage ? 0 : spacing.s4,
							} }
							aria-label={
								hasImage
									? __(
											'Replace hero banner',
											'woocommerce-payments'
									  )
									: __(
											'Upload hero banner',
											'woocommerce-payments'
									  )
							}
						>
							{ ! hasImage && (
								<>
									<svg
										width="28"
										height="28"
										viewBox="0 0 24 24"
										fill="none"
										stroke="currentColor"
										strokeWidth="1.5"
										strokeLinecap="round"
										strokeLinejoin="round"
										aria-hidden="true"
									>
										<rect
											x="3"
											y="3"
											width="18"
											height="18"
											rx="2"
										/>
										<circle cx="8.5" cy="8.5" r="1.5" />
										<path d="M21 15l-5-5L5 21" />
									</svg>
									<span
										style={ {
											fontSize: '13px',
											fontWeight: 600,
											color: colors.textPrimary,
										} }
									>
										{ __(
											'Click to upload an image',
											'woocommerce-payments'
										) }
									</span>
									<span
										style={ {
											fontSize: '12px',
											color: colors.textMuted,
											lineHeight: 1.5,
											maxWidth: '360px',
										} }
									>
										{ __(
											'Featured at the top of your Shopping Network storefront.',
											'woocommerce-payments'
										) }
									</span>
								</>
							) }
						</button>

						{ hasImage && (
							<div
								style={ {
									display: 'flex',
									gap: spacing.s3,
									alignItems: 'center',
									marginTop: spacing.s2,
								} }
							>
								<Button
									variant="link"
									onClick={ safeOpenMediaModal( open ) }
									style={ {
										fontSize: '12px',
										padding: 0,
									} }
								>
									{ __( 'Replace…', 'woocommerce-payments' ) }
								</Button>
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
									{ __( 'Remove', 'woocommerce-payments' ) }
								</Button>
							</div>
						) }
					</>
				) }
			/>
		</div>
	);
};

export default HeroBannerPicker;
