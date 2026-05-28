/**
 * Design tokens for the Woo Shopping Network Hub.
 *
 * LOCAL COPY of AI Storefront design tokens (NOT imported from the AI Storefront
 * plugin) to avoid cross-plugin runtime coupling. Source values pulled from the
 * v2 mockup's inline comments. Keep this file in sync if AI Storefront's tokens
 * change meaningfully — see RSM-2470 plan for the rationale.
 *
 * @format
 */

export const colors = {
	// Brand
	accent: '#720EEC', // WSN purple (CTA, hero gradient, focus)
	accentDark: '#5007AA', // hover state for purple buttons
	accentSoft: '#D1C1FF', // assistant chip background
	accentSoftText: '#2C045D', // assistant chip text

	// Surfaces
	surface: '#fff',
	surfaceMuted: '#f0f0f0',
	surfaceAdmin: '#f6f7f7', // WP admin neutral

	// Text
	textPrimary: '#1d2327',
	textSecondary: '#50575e',
	textMuted: '#757575',
	textPlaceholder: '#a7aaad',

	// Borders
	borderSubtle: '#e0e0e0',
	borderStrong: '#c3c4c7',

	// Status
	infoBg: '#f0f6fc',
	infoBorder: '#2271b1',
	successText: '#00a32a',
	successBg: '#edfaef',
	successBorder: '#c6e1c6',
	dangerText: '#d63638',
	dangerBg: '#fce8e8',
};

export const typography = {
	body: {
		fontFamily:
			'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
		fontSize: '13px',
		lineHeight: '1.5',
		fontWeight: 400,
	},
	brandHeading: {
		fontSize: '15px',
		fontWeight: 500,
		lineHeight: '1.2',
		color: colors.textPrimary,
	},
	sectionHeading: {
		fontSize: '18px',
		fontWeight: 600,
		lineHeight: '1.3',
		color: colors.textPrimary,
	},
	heroHeadline: {
		fontSize: '28px',
		fontWeight: 700,
		letterSpacing: '-0.02em',
		lineHeight: '1.2',
		color: colors.textPrimary,
	},
	heroTagline: {
		fontSize: '15px',
		lineHeight: '1.5',
		fontWeight: 400,
		color: colors.textSecondary,
	},
	eyebrowLabel: {
		fontSize: '12px',
		fontWeight: 600,
		textTransform: 'uppercase',
		letterSpacing: '0.04em',
		color: colors.textMuted,
	},
	statValue: {
		fontSize: '20px',
		fontWeight: 700,
		letterSpacing: '-0.005em',
		color: colors.textPrimary,
	},
};

// 4px spacing scale (s1=4, s2=8, …). Mirrors AI Storefront baseline.
export const spacing = {
	s1: '4px',
	s2: '8px',
	s3: '12px',
	s4: '16px',
	s5: '20px',
	s6: '24px',
	s7: '32px',
};

export const radii = {
	sm: '3px',
	md: '4px',
	pill: '999px',
};

// Responsive breakpoints — WP-admin standard at 781px, narrow phone at 520px.
export const breakpoints = {
	mobile: '781px',
	narrowPhone: '520px',
};
