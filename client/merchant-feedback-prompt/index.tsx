/**
 * External dependencies
 */
import React, { useState } from 'react';
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

export default function MerchantFeedbackPrompt() {
	// Get the core notices, which we'll use to ensure we're not rendering the prompt if there are other notices being displayed.
	const coreNotices = useSelect(
		( select ) =>
			select( 'core/notices' ).getNotices() as NoticeList.Notice[]
	);
	const [ isDismissed, setIsDismissed ] = useState( false );

	function handleDismiss() {
		setIsDismissed( true );
	}

	if ( coreNotices?.length > 0 ) {
		// We don't want to render the prompt if there are other notices being displayed.
		return null;
	}

	if ( isDismissed ) {
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
							<Flex gap={ 3 } align="center">
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
											// eslint-disable-next-line no-console -- temporary action logging
											console.log( 'Yes clicked 👍' );
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
											// eslint-disable-next-line no-console -- temporary action logging
											console.log( 'No clicked 👎' );
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
											'Dismiss this notice'
										) }
										tabIndex={ 0 }
										onClick={ handleDismiss }
										onKeyPress={ handleDismiss }
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
