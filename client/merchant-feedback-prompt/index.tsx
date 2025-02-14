/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import ReactDOM from 'react-dom';
import {
	Button,
	Flex,
	FlexItem,
	Icon,
	NoticeList,
	SnackbarList,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * A portal for the merchant feedback prompt.
 * This is used to render the prompt in the WC footer component, consistent with where WC transient notices are rendered.
 */
const SnackbarPortal = ( { children }: { children: React.ReactNode } ) => {
	const portalRoot = document.getElementsByClassName(
		'woocommerce-layout__footer'
	)[ 0 ];

	if ( ! portalRoot ) {
		return null;
	}

	return ReactDOM.createPortal(
		children,
		portalRoot as HTMLElement // Renders here instead of parent
	);
};

export default function MerchantFeedbackPrompt() {
	const { createNotice } = useDispatch( 'core/notices' );
	const notices = useSelect(
		( select ) =>
			select( 'core/notices' ).getNotices() as NoticeList.Notice[]
	);

	useEffect( () => {
		// Temporary test notice on mount.
		createNotice(
			'info',
			__( 'This is a test notice', 'woocommerce-payments' ),
			{
				explicitDismiss: true,
			}
		);
	}, [ createNotice ] );

	if ( notices?.length > 0 ) {
		// We don't want to render the prompt if there are other notices being displayed.
		return null;
	}

	return (
		<SnackbarPortal>
			<SnackbarList
				className="wcpay-merchant-feedback-prompt-wrap"
				notices={ [
					{
						id: 'merchant-feedback-prompt',
						isDismissible: true,
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
							</Flex>
						),
					},
				] }
			/>
		</SnackbarPortal>
	);
}
