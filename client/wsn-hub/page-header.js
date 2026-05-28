/**
 * PageHeader for the Woo Shopping Network Hub.
 *
 * Direct port of the v2 mockup's .page-header — purple WSN wordmark SVG + h2 title,
 * 8/32/19 padding with a -20px horizontal bleed so the bottom border reaches the
 * full content area. The optical-centering math (top padding 8 vs bottom 19) is
 * documented inline in the mockup CSS.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';
import { colors, typography, radii } from './tokens';

// SVG path data for the WSN purple wordmark, extracted to a constant so the JSX
// can stay readable. The string is a single SVG token and can't be broken without
// changing the rendered shape.
/* eslint-disable max-len */
const WSN_LOGO_PATH =
	'M187.5 375C291.053 375 375 291.053 375 187.5C375 83.9466 291.053 0 187.5 0C83.9466 0 0 83.9466 0 187.5C0 291.053 83.9466 375 187.5 375ZM165.409 242.53C155.31 261.493 141.913 269.737 125.217 269.737C104.4 269.737 90.1786 257.164 90.1786 235.935V160.704H74.308C60.0863 160.704 52.2541 153.49 52.2541 140.917C52.2541 128.345 59.6741 121.543 74.308 121.543H113.057C132.019 121.543 139.851 129.581 139.851 148.544V207.491L173.448 141.742C181.074 126.902 190.967 121.543 203.334 121.543C218.998 121.543 227.449 130.2 227.449 147.925V207.491L263.106 140.505C270.938 125.871 279.595 121.543 292.992 121.543C317.932 121.543 325.97 135.971 314.634 155.139L262.9 242.53C251.152 262.523 238.991 269.737 222.502 269.737C201.479 269.737 187.875 257.164 187.875 236.141V200.484L165.409 242.53Z';
/* eslint-enable max-len */

const WsnLogo = () => (
	<svg
		width="20"
		height="20"
		viewBox="0 0 375 375"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
		focusable="false"
		style={ { flexShrink: 0, display: 'block' } }
	>
		<path
			fillRule="evenodd"
			clipRule="evenodd"
			d={ WSN_LOGO_PATH }
			fill={ colors.accent }
		/>
	</svg>
);

// Mirrors AI Storefront's PageHeader (withNavSlot=true variant):
//   - padding `8px 32px 19px` — optical-centered title within the strip
//   - margin `0 -20px 0` — bleeds out of .wrap's default 20px right
//     padding so the strip fills the content area edge-to-edge
//   - borderBottom 'none' — TabPanel strip below owns the divider
const PageHeader = () => (
	<header
		className="wcpay-wsn-hub__page-header"
		style={ {
			padding: '8px 32px 19px',
			margin: '0 -20px 0',
			background: colors.surface,
			borderBottom: 'none',
		} }
		aria-hidden="true"
	>
		<div
			style={ {
				display: 'flex',
				alignItems: 'center',
				gap: '8px',
			} }
		>
			<WsnLogo />
			<h2
				style={ {
					...typography.brandHeading,
					margin: 0,
					padding: 0,
				} }
			>
				{ __( 'Woo Shopping Network', 'woocommerce-payments' ) }
			</h2>
			{ /* Beta pill: subtle uppercase chip set merchant
			     expectations during active admin use. Remove this
			     entire <span> when the feature reaches GA. */ }
			<span
				style={ {
					...typography.eyebrowLabel,
					background: colors.surfaceMuted,
					color: colors.textSecondary,
					padding: '2px 8px',
					borderRadius: radii.sm,
					lineHeight: 1.2,
					letterSpacing: '0.06em',
				} }
			>
				{ __( 'Beta', 'woocommerce-payments' ) }
			</span>
		</div>
	</header>
);

export default PageHeader;
