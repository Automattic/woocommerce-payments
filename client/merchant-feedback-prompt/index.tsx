/**
 * External dependencies
 */
import React, { useEffect, useState, useRef } from 'react';
import ReactDOM from 'react-dom';
import {
	Button,
	Flex,
	FlexItem,
	Icon,
	NoticeList,
	SnackbarList,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { recordEvent } from 'wcpay/tracks';
import './style.scss';

/**
 * A react portal for the merchant feedback prompt.
 * This is used to render the custom snackbar prompt in the WC footer component, consistent with where WC notices (snackbars) are rendered.
 */
const WCFooterPortal = ( { children }: { children: React.ReactNode } ) => {
	const portalRoot = document.getElementsByClassName(
		'woocommerce-layout__footer'
	)[ 0 ];

	if ( ! portalRoot ) {
		return null;
	}

	return ReactDOM.createPortal( children, portalRoot );
};

/**
 * Renders the merchant feedback prompt (snackbar) in the WC footer.
 *
 * This is used to gather feedback from merchants about their experience with WooPayments.
 * Only renders if there are no core notices and the prompt has not been dismissed.
 */
export default function MerchantFeedbackPrompt() {
	// Get the core notices, which we'll use to ensure we're not rendering the prompt if there are other notices being displayed.
	const coreNotices = useSelect(
		( select ) =>
			select( 'core/notices' ).getNotices() as NoticeList.Notice[]
	);
	// TODO: This is a temporary local state to track if the prompt has been dismissed. Move to a user-persistent state in #10329.
	const [ isDismissed, setIsDismissed ] = useState( false );

	// Create a ref to track if the view event has been recorded to prevent multiple recordings on a single screen.
	const hasRecordedViewEvent = useRef( false );
	const shouldRender = coreNotices?.length === 0 && ! isDismissed;

	function dismissPrompt() {
		setIsDismissed( true );
		recordEvent( 'wcpay_merchant_feedback_prompt_dismiss' );
	}

	useEffect( () => {
		// Record the event when the prompt is rendered, but only once per screen.
		if ( shouldRender && ! hasRecordedViewEvent.current ) {
			recordEvent( 'wcpay_merchant_feedback_prompt_view' );
			hasRecordedViewEvent.current = true;
		}
	}, [ shouldRender ] );

	if ( ! shouldRender ) {
		return null;
	}

	return (
		<WCFooterPortal>
			<SnackbarList
				className="wcpay-merchant-feedback-prompt-wrap"
				notices={ [
					{
						id: 'wcpay-merchant-feedback-prompt',
						className: 'wcpay-merchant-feedback-prompt',
						content: (
							<Flex
								gap={ 3 }
								align="center"
								onClick={ dismissPrompt }
							>
								<FlexItem>
									{ __(
										'Are you satisfied with WooPayments?',
										'woocommerce-payments'
									) }
								</FlexItem>
								<FlexItem>
									<Button
										variant="link"
										className="wcpay-merchant-feedback-prompt__action"
										onClick={ () => {
											recordEvent(
												'wcpay_merchant_feedback_prompt_yes_click'
											);
											dismissPrompt();
										} }
									>
										<Icon
											icon={ 'thumbs-up' }
											aria-label={ __(
												'"Yes" icon',
												'woocommerce-payments'
											) }
										/>
										<span className="wcpay-merchant-feedback-prompt__action-label">
											{ __(
												'Yes',
												'woocommerce-payments'
											) }
										</span>
									</Button>
								</FlexItem>
								<FlexItem>
									<Button
										variant="link"
										className="wcpay-merchant-feedback-prompt__action"
										onClick={ () => {
											recordEvent(
												'wcpay_merchant_feedback_prompt_no_click'
											);
											dismissPrompt();
										} }
									>
										<Icon
											icon={ 'thumbs-down' }
											aria-label={ __(
												'"No" icon',
												'woocommerce-payments'
											) }
										/>
										<span className="wcpay-merchant-feedback-prompt__action-label">
											{ __(
												'No',
												'woocommerce-payments'
											) }
										</span>
									</Button>
								</FlexItem>

								{ /*
									Explicit dismiss button, in place of missing `explicitDismiss` prop.
									We need to implement manually because we use an outdated bundled SnackbarList component which doesn't have the `explicitDismiss` prop.
									See https://github.com/WordPress/gutenberg/blob/c300edfebb48f79f6f0f6643ce04dd73303c5fcb/packages/components/src/snackbar/index.tsx#L166-L177
								*/ }
								<FlexItem>
									<span
										role="button"
										aria-label={ __(
											'Dismiss',
											'woocommerce-payments'
										) }
										tabIndex={ 0 }
										onClick={ dismissPrompt }
										onKeyPress={ dismissPrompt }
									>
										{ /* Unicode character for "close" icon */ }
										&#x2715;
									</span>
								</FlexItem>
							</Flex>
						),
					},
				] }
			/>
		</WCFooterPortal>
	);
}
