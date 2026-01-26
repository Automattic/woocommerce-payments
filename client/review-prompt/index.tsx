/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { useReviewPromptState } from './hooks';
import { recordEvent } from 'wcpay/tracks';

const wordpressOrgReviewUrl =
	'https://wordpress.org/support/plugin/woocommerce-payments/reviews/#new-post';
const marketplaceReviewUrl =
	'https://woocommerce.com/products/woocommerce-payments/#reviews';

const ReviewPrompt: React.FC = () => {
	const {
		isFeatureEnabled,
		isAccountEligible,
		hasUserDismissedPrompt,
		isCooldownActive,
		dismissPrompt,
		setMaybeLater,
	} = useReviewPromptState();

	// Determine destination based on connection state
	const isLive = wcpaySettings?.accountStatus?.isLive;
	const destinationType = isLive ? 'wordpress_org' : 'marketplace';
	const reviewUrl = isLive ? wordpressOrgReviewUrl : marketplaceReviewUrl;

	const getEventProperties = useCallback(
		() => ( {
			source: 'wc-settings-payments',
			destination: destinationType,
		} ),
		[ destinationType ]
	);

	const handleView = useCallback( () => {
		recordEvent( 'wcpay_review_prompt_view', getEventProperties() );
	}, [ getEventProperties ] );

	const handlePrimaryClick = useCallback( () => {
		recordEvent(
			'wcpay_review_prompt_write_review_click',
			getEventProperties()
		);
		window.open( reviewUrl, '_blank', 'noopener,noreferrer' );
		dismissPrompt();
	}, [ reviewUrl, getEventProperties, dismissPrompt ] );

	const handleSecondaryClick = useCallback( () => {
		recordEvent(
			'wcpay_review_prompt_maybe_later_click',
			getEventProperties()
		);
		setMaybeLater();
	}, [ getEventProperties, setMaybeLater ] );

	const handleDismiss = useCallback( () => {
		recordEvent( 'wcpay_review_prompt_dismiss', getEventProperties() );
		dismissPrompt();
	}, [ getEventProperties, dismissPrompt ] );

	// Don't render if feature disabled, account not eligible, dismissed, or cooldown active
	if (
		! isFeatureEnabled ||
		! isAccountEligible ||
		hasUserDismissedPrompt ||
		isCooldownActive
	) {
		return null;
	}

	return (
		<Spotlight
			heading={ __(
				'Enjoying WooPayments so far?',
				'woocommerce-payments'
			) }
			description={ __(
				'Your feedback shapes our roadmap and supports the WooCommerce community. We are all ears!',
				'woocommerce-payments'
			) }
			primaryButtonLabel={ __(
				'Write a review',
				'woocommerce-payments'
			) }
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ __( 'Maybe later', 'woocommerce-payments' ) }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
			showImmediately={ false }
		/>
	);
};

export default ReviewPrompt;
