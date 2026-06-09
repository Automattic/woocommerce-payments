/**
 * PreEnableHero — the view shown when WSN is disabled.
 *
 * Mirrors the v2 mockup's pre-enable view: a hero headline + tagline + 3-chip
 * value strip + brand-purple CTA + reassurance line, followed by a 3-up
 * "value cards" grid below. The CTA dispatches an enable action that flips
 * `wcpay_wsn_enabled` to `'1'`; on success, the parent OverviewTab re-renders
 * into the OverviewDashboard.
 *
 * Owned by RSM-2493.
 *
 * @format
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { colors, typography, spacing, radii } from '../tokens';
import { formatApiError } from '../utils/format-api-error';

const IconUsers = () => (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		stroke={ colors.accent }
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
		<circle cx="9" cy="7" r="4" />
		<path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
	</svg>
);

const IconCheckout = () => (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		stroke={ colors.accent }
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
		<line x1="1" y1="10" x2="23" y2="10" />
		<path d="M5 16h3M12 16h2" />
	</svg>
);

const IconAttribution = () => (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		stroke={ colors.accent }
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<line x1="18" y1="20" x2="18" y2="10" />
		<line x1="12" y1="20" x2="12" y2="4" />
		<line x1="6" y1="20" x2="6" y2="14" />
		<path d="M2 20h20" />
	</svg>
);

// Translate strings at construction time. The eslint rule
// `@wordpress/i18n-no-variables` forbids passing variables to `__()` so we
// can't store the English source separately and translate later — both the
// title and body must be string literals at the call site.
const VALUE_CARDS = [
	{
		Icon: IconUsers,
		title: __( 'Find new buyers', 'woocommerce-payments' ),
		body: __(
			'Reach an existing community of millions of shoppers who already use WooPay.',
			'woocommerce-payments'
		),
	},
	{
		Icon: IconCheckout,
		title: __( 'Zero checkout friction', 'woocommerce-payments' ),
		body: __(
			'1-click checkout. Revenue flows through your existing WooPayments account.',
			'woocommerce-payments'
		),
	},
	{
		Icon: IconAttribution,
		title: __( 'Attribution built in', 'woocommerce-payments' ),
		body: __(
			'Every order is tagged automatically, visible in your Overview stats the moment the first purchase lands.',
			'woocommerce-payments'
		),
	},
];

/**
 * @param {Object}   props
 * @param {Function} props.onEnabled Called after the enable request succeeds.
 */
const PreEnableHero = ( { onEnabled } ) => {
	const [ isEnabling, setIsEnabling ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleEnable = async () => {
		setIsEnabling( true );
		setError( null );
		try {
			await apiFetch( {
				path: '/wc/v3/payments/wsn/settings',
				method: 'PUT',
				data: { enabled: true },
			} );
			onEnabled();
		} catch ( e ) {
			setError( formatApiError( e ) );
			setIsEnabling( false );
		}
	};

	return (
		<div>
			<div
				style={ {
					background: 'linear-gradient(135deg, #faf6ff, #fff 60%)',
					border: `1px solid ${ colors.borderSubtle }`,
					borderRadius: radii.sm,
					padding: `${ spacing.s7 } ${ spacing.s6 }`,
					marginBottom: spacing.s6,
				} }
			>
				<h2
					style={ {
						...typography.heroHeadline,
						color: colors.textPrimary,
						margin: `0 0 ${ spacing.s1 }`,
					} }
				>
					{ __(
						'List once. Sell to millions of Woo shoppers.',
						'woocommerce-payments'
					) }
				</h2>
				<p
					style={ {
						...typography.heroTagline,
						color: colors.textSecondary,
						margin: `0 0 ${ spacing.s5 }`,
					} }
				>
					{ __(
						'Reach new shoppers. Earn repeat buyers.',
						'woocommerce-payments'
					) }
				</p>

				<div
					style={ {
						display: 'grid',
						gridTemplateColumns: 'repeat(3, max-content)',
						gap: spacing.s2,
						marginBottom: spacing.s6,
					} }
				>
					{ [
						__( 'Acquire new customers', 'woocommerce-payments' ),
						__( 'Branded storefront', 'woocommerce-payments' ),
						__( 'Built-in attribution', 'woocommerce-payments' ),
					].map( ( chipText ) => (
						<span
							key={ chipText }
							style={ {
								background: colors.accentSoft,
								borderRadius: radii.pill,
								padding: '5px 12px',
								fontSize: '12px',
								fontWeight: 600,
								color: colors.accentSoftText,
								display: 'inline-flex',
								alignItems: 'center',
								lineHeight: 1,
							} }
						>
							{ chipText }
						</span>
					) ) }
				</div>

				<button
					type="button"
					onClick={ handleEnable }
					disabled={ isEnabling }
					style={ {
						background: colors.accent,
						color: colors.surface,
						border: `1px solid ${ colors.accent }`,
						borderRadius: radii.sm,
						padding: '8px 16px',
						fontSize: '14px',
						fontWeight: 600,
						lineHeight: 1,
						cursor: isEnabling ? 'progress' : 'pointer',
						opacity: isEnabling ? 0.7 : 1,
					} }
				>
					{ isEnabling
						? __( 'Enabling…', 'woocommerce-payments' )
						: __(
								'Enable Woo Shopping Network',
								'woocommerce-payments'
						  ) }
				</button>
				<p
					style={ {
						margin: `${ spacing.s3 } 0 0`,
						fontSize: '12px',
						color: colors.textMuted,
						lineHeight: 1.5,
					} }
				>
					{ __(
						'Reversible anytime · Settings preserved',
						'woocommerce-payments'
					) }
				</p>
				{ error && (
					<p
						role="alert"
						style={ {
							margin: `${ spacing.s3 } 0 0`,
							fontSize: '12px',
							color: colors.dangerText,
						} }
					>
						{ error }
					</p>
				) }
			</div>

			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
					gap: spacing.s4,
				} }
			>
				{ VALUE_CARDS.map( ( { Icon, title, body } ) => (
					<div
						key={ title }
						style={ {
							background: colors.surfaceAdmin,
							border: `1px solid ${ colors.borderSubtle }`,
							borderRadius: radii.sm,
							padding: spacing.s4,
						} }
					>
						<div style={ { marginBottom: spacing.s3 } }>
							<Icon />
						</div>
						<div
							style={ {
								fontSize: '15px',
								fontWeight: 600,
								color: colors.textPrimary,
								marginBottom: '6px',
							} }
						>
							{ title }
						</div>
						<div
							style={ {
								fontSize: '13px',
								color: colors.textSecondary,
								lineHeight: 1.6,
							} }
						>
							{ body }
						</div>
					</div>
				) ) }
			</div>
		</div>
	);
};

export default PreEnableHero;
