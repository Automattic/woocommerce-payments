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
import { Promotion } from 'data/promotions/types';
import { recordEvent } from 'tracks';

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
 * - Filters for 'spotlight' type promotions
 * - Handles activation and dismissal of promotions
 */
const SpotlightPromotion: React.FC = () => {
	const { promotions, isLoading } = usePromotions();
	const { activatePromotion, dismissPromotion } = usePromotionActions();

	// Don't render if data is still loading
	if ( isLoading ) {
		return null;
	}

	// Don't render if no promotions available
	if ( ! promotions || promotions.length === 0 ) {
		return null;
	}

	// Find the first spotlight promotion
	const spotlightPromotion: Promotion | undefined = promotions.find(
		( promo ) => promo.type === 'spotlight'
	);

	// No spotlight promotion available
	if ( ! spotlightPromotion ) {
		return null;
	}

	/**
	 * Get common event properties for tracking.
	 */
	const getEventProperties = () => ( {
		promo_id: spotlightPromotion.promo_id,
		payment_method: spotlightPromotion.payment_method,
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
		recordEvent(
			'wcpay_payment_method_promotion_activate_click',
			getEventProperties()
		);
		activatePromotion( spotlightPromotion.promo_id );
	};

	const handleSecondaryClick = () => {
		recordEvent( 'wcpay_payment_method_promotion_link_click', {
			...getEventProperties(),
			link_type: 'terms',
		} );
		if ( spotlightPromotion.tc_url ) {
			try {
				const parsedUrl = new URL( spotlightPromotion.tc_url );
				if (
					parsedUrl.protocol === 'https:' ||
					parsedUrl.protocol === 'http:'
				) {
					window.open(
						spotlightPromotion.tc_url,
						'_blank',
						'noopener,noreferrer'
					);
				}
			} catch {
				// Invalid URL, don't open
			}
		}
	};

	const handleDismiss = () => {
		recordEvent(
			'wcpay_payment_method_promotion_dismiss_click',
			getEventProperties()
		);
		dismissPromotion( spotlightPromotion.id );
	};

	return (
		<Spotlight
			heading={ spotlightPromotion.title }
			description={ spotlightPromotion.description }
			footnote={ spotlightPromotion.footnote }
			image={ spotlightPromotion.image }
			primaryButtonLabel={ spotlightPromotion.cta_label }
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ spotlightPromotion.tc_label }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
		/>
	);
};

export default SpotlightPromotion;
