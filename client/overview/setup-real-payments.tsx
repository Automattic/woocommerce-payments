/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Modal,
} from '@wordpress/components';
import { Icon, payment, globe, currencyDollar } from '@wordpress/icons';
import ScheduledIcon from 'gridicons/dist/scheduled';

/**
 * Internal dependencies
 */
import BlockEmbedIcon from 'components/icons/block-embed';
import BlockPostAuthorIcon from 'components/icons/block-post-author';

const SetupRealPayments: React.FC = () => {
	const [ modalVisible, setModalVisible ] = useState( false );

	const handleContinue = () => {
		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			'wcpay-disable-onboarding-test-mode': true,
		} );
	};

	return (
		<>
			<Card className="wcpay-setup-real-payments">
				<CardHeader>
					{ __(
						'Ready to setup real payments on your store?',
						'woocommerce-payments'
					) }
				</CardHeader>
				<CardBody className="wcpay-setup-real-payments__body">
					<div>
						<Icon icon={ payment } size={ 32 } />
						{ __(
							'Offer a wide range of card payments',
							'woocommerce-payments'
						) }
					</div>
					<div>
						<Icon icon={ globe } size={ 32 } />
						{ __(
							'135 different currencies and local payment methods',
							'woocommerce-payments'
						) }
					</div>
					<div>
						<ScheduledIcon size={ 32 } />
						{ __(
							'Enjoy direct deposits into your bank account',
							'woocommerce-payments'
						) }
					</div>
				</CardBody>
				<CardFooter className="wcpay-setup-real-payments__footer">
					<Button isPrimary onClick={ () => setModalVisible( true ) }>
						{ __( 'Set up payments', 'woocommerce-payments' ) }
					</Button>
				</CardFooter>
			</Card>
			{ modalVisible && (
				<Modal
					title={ __(
						'Setup live payments on your store',
						'woocommerce-payments'
					) }
					className="wcpay-setup-real-payments-modal"
					isDismissible={ true }
					onRequestClose={ () => setModalVisible( false ) }
				>
					<p className="wcpay-setup-real-payments-modal__headline">
						{ __(
							'Before proceeding, please take note of the following information:',
							'woocommerce-payments'
						) }
					</p>
					<div className="wcpay-setup-real-payments-modal__content">
						<Icon icon={ BlockEmbedIcon } />
						{ __(
							'Your test account will be deactivated and your transaction records will be preserved for future reference.',
							'woocommerce-payments'
						) }
						<Icon icon={ BlockPostAuthorIcon } />
						{ __(
							'The owner, business and contact information will be required.',
							'woocommerce-payments'
						) }
						<Icon icon={ currencyDollar } />
						{ __(
							'We will need your banking details in order to process any payouts to you.',
							'woocommerce-payments'
						) }
					</div>
					<div className="wcpay-setup-real-payments-modal__footer">
						<Button
							isTertiary
							onClick={ () => setModalVisible( false ) }
						>
							{ __( 'Go back', 'woocommerce-payments' ) }
						</Button>
						<Button isPrimary onClick={ handleContinue }>
							{ __( 'Continue setup', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default SetupRealPayments;
