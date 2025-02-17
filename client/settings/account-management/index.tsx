/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Card, CardBody } from '@wordpress/components';
import {
	ConnectAccountManagement,
	ConnectComponentsProvider,
} from '@stripe/react-connect-js';

/**
 * Internal dependencies
 */
import StripeSpinner from 'wcpay/components/stripe-spinner';
import BannerNotice from 'wcpay/components/banner-notice';
import appearance from 'wcpay/utils/embedded-components/appearance';
import useAccountSession from 'wcpay/utils/embedded-components/account-session';
import './style.scss';

const AccountManagement = () => {
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const stripeConnectInstance = useAccountSession( {
		setLoadErrorMessage,
		appearance,
	} );

	return (
		<>
			<Card>
				<CardBody>
					{ loading && (
						<div className="account-management-loader-wrapper">
							<StripeSpinner />
						</div>
					) }
					{ loadErrorMessage ? (
						<BannerNotice status="error">
							{ loadErrorMessage }
						</BannerNotice>
					) : (
						stripeConnectInstance && (
							<ConnectComponentsProvider
								connectInstance={ stripeConnectInstance }
							>
								<ConnectAccountManagement
									onLoaderStart={ () => setLoading( false ) }
									onLoadError={ ( loadError ) =>
										setLoadErrorMessage(
											loadError.error.message ||
												'Unknown error'
										)
									}
									collectionOptions={ {
										fields: 'eventually_due',
										futureRequirements: 'include',
									} }
								/>
							</ConnectComponentsProvider>
						)
					) }
				</CardBody>
			</Card>
		</>
	);
};

export default AccountManagement;
