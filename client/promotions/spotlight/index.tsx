/** @format */

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { usePromotions, usePromotionActions } from 'data';
import { Promotion, PromotionVariation } from 'data/promotions/types';
import { recordEvent } from 'tracks';
import KlarnaIllustration from 'assets/images/illustrations/klarna-promotion-spotlight.svg?asset';

/**
 * Mapping of promotion IDs to their corresponding spotlight images.
 * Images are bundled as local assets for fast loading and version control.
 * Promotions without a mapped image will render without an image.
 */
const spotlightImages: Record< string, string > = {
	'klarna-2026-promo': KlarnaIllustration,
	// Add more promotion images here as needed
};

/**
 * Determine a human-readable source identifier based on the current page.
 *
 * @return {string} Source identifier for tracking.
 */
const getPageSource = (): string => {
	const path = window.location.pathname + window.location.search;

	if ( path.includes( 'path=%2Fpayments%2Foverview' ) ) {
		return 'wcpay-overview';
	}
	if ( path.includes( 'path=%2Fpayments%2Fsettings' ) ) {
		return 'wcpay-settings';
	}
	if (
		path.includes( 'page=wc-settings' ) &&
		path.includes( 'tab=checkout' )
	) {
		return 'wc-settings-payments';
	}

	return 'unknown';
};

/**
 * Container component that fetches promotions and renders the Spotlight component.
 *
 * This component:
 * - Fetches promotions from the API
 * - Filters for 'spotlight' type variations
 * - Only displays if account is onboarded (status is 'complete' or 'enabled')
 * - Handles activation and dismissal of promotions
 */
const SpotlightPromotion: React.FC = () => {
	const { promotions, isLoading } = usePromotions();
	const { activatePromotion, dismissPromotion } = usePromotionActions();

	// Check if account is onboarded - only show if status is 'complete' or 'enabled'
	const accountStatus = wcpaySettings?.accountStatus?.status;
	const isAccountOnboarded =
		accountStatus === 'complete' || accountStatus === 'enabled';

	// Don't render if account is not onboarded or data is still loading
	if ( ! isAccountOnboarded || isLoading ) {
		return null;
	}

	// Don't render if no promotions available
	if ( ! promotions || promotions.length === 0 ) {
		return null;
	}

	// Find the first available promotion with a 'spotlight' variation
	let spotlightVariation: PromotionVariation | null = null;
	let activePromotion: Promotion | null = null;

	for ( const promotion of promotions ) {
		const variation = promotion.variations.find(
			( v ) => v.type === 'spotlight'
		);
		if ( variation ) {
			spotlightVariation = variation;
			activePromotion = promotion;
			break;
		}
	}

	// No spotlight promotion available
	if ( ! spotlightVariation || ! activePromotion ) {
		return null;
	}

	// Extract values after null check for TypeScript
	const promotionId = activePromotion.promo_id;
	const paymentMethod = activePromotion.payment_method;
	const variationId = spotlightVariation.id;
	const ctaLabel = spotlightVariation.cta_label;
	const ctaUrl = spotlightVariation.cta_url;

	/**
	 * Get common event properties for tracking.
	 */
	const getEventProperties = () => ( {
		promotion_id: promotionId,
		payment_method: paymentMethod,
		variation_id: variationId,
		display_context: 'spotlight',
		source: getPageSource(),
		path: window.location.pathname + window.location.search,
	} );

	const handleView = () => {
		recordEvent(
			'wcpay_payment_method_promotion_view',
			getEventProperties()
		);
	};

	const handlePrimaryClick = () => {
		recordEvent( 'wcpay_payment_method_promotion_cta_click', {
			...getEventProperties(),
			cta_label: ctaLabel,
		} );
		activatePromotion( promotionId );
	};

	const handleSecondaryClick = () => {
		recordEvent(
			'wcpay_payment_method_promotion_secondary_click',
			getEventProperties()
		);
		if ( ctaUrl ) {
			window.open( ctaUrl, '_blank' );
		}
	};

	const handleDismiss = () => {
		recordEvent(
			'wcpay_payment_method_promotion_dismiss',
			getEventProperties()
		);
		dismissPromotion( promotionId, variationId as string );
	};

	const handleTermsClick = () => {
		recordEvent( 'wcpay_payment_method_promotion_link_click', {
			...getEventProperties(),
			link_type: 'terms',
		} );
	};

	// Build disclaimer content if footnote and tc_url exist
	let disclaimer: React.ReactNode | undefined;
	if ( spotlightVariation.footnote && spotlightVariation.tc_url ) {
		disclaimer = (
			<>
				{ spotlightVariation.footnote }{ ' ' }
				<a
					href={ spotlightVariation.tc_url }
					target="_blank"
					rel="noopener noreferrer"
					onClick={ handleTermsClick }
				>
					Terms and conditions
				</a>
			</>
		);
	} else if ( spotlightVariation.footnote ) {
		disclaimer = spotlightVariation.footnote;
	}

	// Get the image for this promotion (undefined if not mapped)
	const image = spotlightImages[ promotionId ];

	return (
		<Spotlight
			badge={ spotlightVariation.badge }
			heading={ spotlightVariation.heading }
			description={ spotlightVariation.description }
			disclaimer={ disclaimer }
			image={ image }
			primaryButtonLabel={ spotlightVariation.cta_label }
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel="Learn more"
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
		/>
	);
};

export default SpotlightPromotion;
