/**
 * External dependencies
 */
import React, { useCallback, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { useReviewPromptState } from './hooks';
import { recordEvent } from 'wcpay/tracks';

const reviewUrl =
	'https://woocommerce.com/products/woocommerce-payments/#reviews';

const ReviewPrompt: React.FC = () => {
	const {
		isAccountEligible,
		hasUserDismissedPrompt,
		isCooldownActive,
		dismissPrompt,
		setMaybeLater,
	} = useReviewPromptState();

	const [ viewTimestamp, setViewTimestamp ] = useState< number | null >(
		null
	);

	// Base event properties per PRO2-35 telemetry requirements
	const getBaseEventProperties = useCallback( () => {
		return {
			prompt_id: 'phase0_payments_settings_001',
			extension: 'woopayments',
			location: 'payments_settings_top_level',
			trigger: 'none',
			flag_enabled: isAccountEligible,
			version: wcpaySettings?.version || 'unknown',
		};
	}, [ isAccountEligible ] );

	const handleView = useCallback( () => {
		const timestamp = Date.now();
		setViewTimestamp( timestamp );
		recordEvent( 'payments_review_prompt_shown', getBaseEventProperties() );
	}, [ getBaseEventProperties ] );

	const handlePrimaryClick = useCallback( () => {
		const timeToClickMs = viewTimestamp ? Date.now() - viewTimestamp : null;
		const eventProps = {
			...getBaseEventProperties(),
			action: 'write_review',
			destination: 'marketplace',
			...( timeToClickMs !== null && {
				time_to_click_ms: timeToClickMs,
			} ),
		};

		recordEvent( 'payments_review_prompt_action', eventProps );
		recordEvent( 'payments_review_destination_selected', eventProps );

		window.open( reviewUrl, '_blank', 'noopener,noreferrer' );
		dismissPrompt();
	}, [ viewTimestamp, getBaseEventProperties, dismissPrompt ] );

	const handleSecondaryClick = useCallback( () => {
		const timeToClickMs = viewTimestamp ? Date.now() - viewTimestamp : null;
		const eventProps = {
			...getBaseEventProperties(),
			action: 'maybe_later',
			...( timeToClickMs !== null && {
				time_to_click_ms: timeToClickMs,
			} ),
		};

		recordEvent( 'payments_review_prompt_action', eventProps );
		setMaybeLater();
	}, [ viewTimestamp, getBaseEventProperties, setMaybeLater ] );

	const handleDismiss = useCallback( () => {
		const timeToClickMs = viewTimestamp ? Date.now() - viewTimestamp : null;
		const eventProps = {
			...getBaseEventProperties(),
			action: 'dismiss_x',
			...( timeToClickMs !== null && {
				time_to_click_ms: timeToClickMs,
			} ),
		};

		recordEvent( 'payments_review_prompt_action', eventProps );
		dismissPrompt();
	}, [ viewTimestamp, getBaseEventProperties, dismissPrompt ] );

	// Track suppression reasons
	if ( ! isAccountEligible ) {
		recordEvent( 'payments_review_prompt_suppressed', {
			...getBaseEventProperties(),
			reason: 'not_eligible',
		} );
		return null;
	}

	if ( hasUserDismissedPrompt ) {
		recordEvent( 'payments_review_prompt_suppressed', {
			...getBaseEventProperties(),
			reason: 'dismissed_permanent',
		} );
		return null;
	}

	if ( isCooldownActive ) {
		recordEvent( 'payments_review_prompt_suppressed', {
			...getBaseEventProperties(),
			reason: 'cooldown_active',
		} );
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
