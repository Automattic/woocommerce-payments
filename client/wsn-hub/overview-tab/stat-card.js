/**
 * StatCard — single statistic primitive used in the Overview dashboard grid.
 *
 * Mirrors AI Storefront's StatCard pattern at
 * `woocommerce-ai-storefront/client/settings/ai-storefront/settings-page.js:617-695`:
 *
 *   - `label` is the all-caps eyebrow above the value.
 *   - `value` is the headline number/text. Pass `null`/`undefined` to render `—`
 *     so empty-state cards line up with populated ones visually.
 *   - `reference` is an optional denominator inlined after the value
 *     (e.g., "41 / 156"). Subordinate weight + muted color so the primary value
 *     reads first. Used for "Network Orders / Total Orders" style framing.
 *
 * Cards are always neutral textPrimary color — no sentiment coloring (red/green)
 * because that mixes channels (good vs. category) and breaks future delta rows.
 *
 * @format
 */

import { colors, typography, radii } from '../tokens';

const StatCard = ( { label, value, reference } ) => {
	const renderValue = value === null || value === undefined ? '—' : value;

	return (
		<div
			style={ {
				background: colors.surface,
				border: `1px solid ${ colors.borderSubtle }`,
				borderRadius: radii.sm,
				padding: '14px 16px',
			} }
		>
			<div
				style={ {
					...typography.eyebrowLabel,
					color: colors.textMuted,
					marginBottom: '6px',
				} }
			>
				{ label }
			</div>
			<div
				style={ {
					...typography.statValue,
					color: colors.textPrimary,
					overflowWrap: 'anywhere',
				} }
			>
				{ renderValue }
				{ reference !== null && reference !== undefined && (
					<span
						style={ {
							marginLeft: '6px',
							fontSize: '14px',
							fontWeight: 400,
							color: colors.textMuted,
							letterSpacing: 'normal',
						} }
					>
						/ { reference }
					</span>
				) }
			</div>
		</div>
	);
};

export default StatCard;
