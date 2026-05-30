/**
 * SyncBadge — green "auto-synced" affordance shown under the Theme Branding
 * row in the Profile tab Branding card.
 *
 * Two states depending on the merchant's theme type:
 *
 *   - **Block theme** (theme.json available) → "Auto-synced from your active
 *     block theme" + last-updated timestamp. The styles cache pulls from
 *     theme.json server-side on demand.
 *
 *   - **Classic theme** → "Pending — recomputes on next checkout view". The
 *     styles cache populates via shopper-side DOM extraction at checkout,
 *     not from any server-side source, so a merchant who changes their
 *     classic theme won't see WSN reflect it until traffic arrives.
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';

import { colors, radii, spacing } from '../tokens';

/**
 * @param {Object}            props
 * @param {'block'|'classic'} props.themeType
 */
const SyncBadge = ( { themeType } ) => {
	const isBlock = themeType === 'block';

	const message = isBlock
		? __(
				'Colors and typography auto-synced from your active block theme.',
				'woocommerce-payments'
		  )
		: __(
				'Pending — recomputes on next checkout view (classic themes need shopper traffic to extract styles).',
				'woocommerce-payments'
		  );

	return (
		<div
			style={ {
				display: 'inline-flex',
				alignItems: 'center',
				gap: spacing.s1,
				fontSize: '12px',
				color: isBlock ? colors.successText : colors.textSecondary,
				background: isBlock ? colors.successBg : colors.surfaceAdmin,
				border: `1px solid ${
					isBlock ? colors.successBorder : colors.borderSubtle
				}`,
				borderRadius: radii.sm,
				padding: '6px 10px',
				marginTop: spacing.s1,
			} }
		>
			<svg
				width="14"
				height="14"
				viewBox="0 0 14 14"
				fill="none"
				stroke={ isBlock ? colors.successText : colors.textMuted }
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
				aria-hidden="true"
			>
				{ isBlock ? (
					<path d="M2.5 7.5l3 3 6-6" />
				) : (
					<>
						<circle cx="7" cy="7" r="5" />
						<path d="M7 4v3l2 2" />
					</>
				) }
			</svg>
			{ message }
		</div>
	);
};

export default SyncBadge;
