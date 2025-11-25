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
import { PromotionVariation } from 'data/promotions/types';
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
	let promotionId: string | null = null;

	for ( const promotion of promotions ) {
		const variation = promotion.variations.find(
			( v ) => v.type === 'spotlight'
		);
		if ( variation ) {
			spotlightVariation = variation;
			promotionId = promotion.promo_id;
			break;
		}
	}

	// No spotlight promotion available
	if ( ! spotlightVariation || ! promotionId ) {
		return null;
	}

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
				>
					Terms and conditions
				</a>
			</>
		);
	} else if ( spotlightVariation.footnote ) {
		disclaimer = spotlightVariation.footnote;
	}

	const handlePrimaryClick = () => {
		activatePromotion( promotionId as string );
	};

	const handleSecondaryClick = () => {
		if ( spotlightVariation?.cta_url ) {
			window.open( spotlightVariation.cta_url, '_blank' );
		}
	};

	const handleDismiss = () => {
		if ( ! spotlightVariation ) {
			return;
		}
		dismissPromotion(
			promotionId as string,
			spotlightVariation.id as string
		);
	};

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
		/>
	);
};

export default SpotlightPromotion;
