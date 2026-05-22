/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Card, CardBody } from '@wordpress/components';
import { LoadError } from '@stripe/connect-js';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import BannerNotice from 'wcpay/components/banner-notice';
import StripeSpinner from 'wcpay/components/stripe-spinner';
import { EmbeddedAccountManagement } from 'wcpay/embedded-components';

const AccountManagement = () => {
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState< LoadError | null >( null );

	const handleLoadError = ( err: LoadError ) => {
		setLoadError( err );
	};

	return (
		<>
			<Card>
				<CardBody>
					{ loading && (
						<div className="account-management-loader-wrapper">
							<StripeSpinner />
						</div>
					) }
					{ loadError &&
						( loadError.error.type === 'invalid_request_error' ? (
							<BannerNotice
								icon={ true }
								status="warning"
								isDismissible={ false }
								actions={ [
									{
										label: 'Learn more',
										variant: 'primary',
										url:
											'https://woocommerce.com/document/woopayments/startup-guide/#requirements',
										urlTarget: '_blank',
									},
								] }
							>
								{ __(
									'Account management through our financial partner requires HTTPS and cannot be completed.',
									'woocommerce-payments'
								) }
							</BannerNotice>
						) : (
							<BannerNotice
								status="error"
								isDismissible={ false }
							>
								{ loadError.error.message }
							</BannerNotice>
						) ) }
					<EmbeddedAccountManagement
						onLoaderStart={ () => setLoading( false ) }
						onLoadError={ handleLoadError }
					></EmbeddedAccountManagement>
				</CardBody>
			</Card>
		</>
	);
};

export default AccountManagement;
