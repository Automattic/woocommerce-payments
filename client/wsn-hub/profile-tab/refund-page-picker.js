/**
 * RefundPagePicker — <select> dropdown of merchant's published Pages,
 * organized as:
 *
 *   1. Policy candidates (top group): pages from WC's
 *      woocommerce_refund_returns_page_id / woocommerce_terms_page_id,
 *      WP's wp_page_for_privacy_policy, plus any page whose title contains
 *      'refund' / 'return' / 'policy' / 'terms' / 'privacy'.
 *   2. All other published pages, alphabetical.
 *
 * Functional WC pages (cart, checkout, shop, my-account) are filtered out
 * server-side.
 *
 * Stores the page ID locally; the resolved URL + title come back from the
 * settings GET derivations so the server is the single source of truth.
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { colors, spacing, radii } from '../tokens';

/**
 * @param {Object}      props
 * @param {number|null} props.pageId   Currently-selected page ID, or null when unset.
 * @param {string|null} props.editUrl  Edit-page URL for the currently-selected page (or null).
 * @param {Function}    props.onChange Called with the new page ID (or null when sentinel is picked).
 */
const RefundPagePicker = ( { pageId, editUrl, onChange } ) => {
	const [ policyPages, setPolicyPages ] = useState( [] );
	const [ otherPages, setOtherPages ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wc/v3/payments/wsn/pages' } )
			.then( ( payload ) => {
				if ( cancelled ) return;
				setPolicyPages( payload?.policy_pages ?? [] );
				setOtherPages( payload?.other_pages ?? [] );
				setIsLoading( false );
			} )
			.catch( () => {
				if ( ! cancelled ) setIsLoading( false );
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const handleChange = ( e ) => {
		const raw = e.target.value;
		onChange( raw === '' ? null : Number( raw ) );
	};

	return (
		<div
			style={ {
				display: 'flex',
				flexDirection: 'column',
				gap: spacing.s1,
			} }
		>
			<label
				htmlFor="wcpay-wsn-refund-page-picker"
				style={ {
					fontSize: '11px',
					fontWeight: 600,
					textTransform: 'uppercase',
					letterSpacing: '0.04em',
					color: colors.textMuted,
				} }
			>
				{ __( 'Refund policy page', 'woocommerce-payments' ) }
			</label>
			<select
				id="wcpay-wsn-refund-page-picker"
				value={ pageId ?? '' }
				onChange={ handleChange }
				disabled={ isLoading }
				style={ {
					border: `1px solid ${ colors.borderStrong }`,
					borderRadius: radii.sm,
					padding: '7px 28px 7px 10px',
					fontSize: '13px',
					color: colors.textPrimary,
					background: colors.surface,
					cursor: 'pointer',
					appearance: 'none',
				} }
			>
				<option value="">
					{ __( '— Select a page —', 'woocommerce-payments' ) }
				</option>

				{ policyPages.length > 0 && (
					<optgroup
						label={ __( 'Suggested', 'woocommerce-payments' ) }
					>
						{ policyPages.map( ( page ) => (
							<option key={ page.id } value={ page.id }>
								{ page.title }
							</option>
						) ) }
					</optgroup>
				) }

				{ otherPages.length > 0 && (
					<optgroup
						label={ __( 'Other pages', 'woocommerce-payments' ) }
					>
						{ otherPages.map( ( page ) => (
							<option key={ page.id } value={ page.id }>
								{ page.title }
							</option>
						) ) }
					</optgroup>
				) }
			</select>

			<span
				style={ {
					fontSize: '11px',
					color: colors.textMuted,
					marginTop: '3px',
				} }
			>
				{ pageId && editUrl ? (
					<>
						{ __(
							'Shown to shoppers from your Shopping Network storefront.',
							'woocommerce-payments'
						) }{ ' ' }
						<a
							href={ editUrl }
							style={ { color: colors.infoBorder } }
						>
							{ __( 'Edit page', 'woocommerce-payments' ) }
						</a>
					</>
				) : (
					__(
						'Pick a published page that explains your refund and ' +
							'returns policy. Functional WooCommerce pages (cart, ' +
							'checkout) are excluded.',
						'woocommerce-payments'
					)
				) }
			</span>
		</div>
	);
};

export default RefundPagePicker;
