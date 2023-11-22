/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Flex,
} from '@wordpress/components';
import { Icon, payment, globe } from '@wordpress/icons';
import ScheduledIcon from 'gridicons/dist/scheduled';

/**
 * Internal dependencies
 */
import SetupLivePaymentsModal from './modal/setup-live-payments';

const SetupRealPayments: React.FC = () => {
	const [ modalVisible, setModalVisible ] = useState( false );

	return (
		<>
			<Card className="wcpay-setup-real-payments">
				<CardHeader>
					{ __(
						'Set up real payments on your store',
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
					<Flex align="center" justify="flex-start">
						<Button
							variant={ 'secondary' }
							onClick={ () => {
								setModalVisible( true );
							} }
						>
							{ __( 'Set up payments', 'woocommerce-payments' ) }
						</Button>
					</Flex>
				</CardFooter>
			</Card>
			{ modalVisible && (
				<SetupLivePaymentsModal
					closeModal={ () => setModalVisible( false ) }
				/>
			) }
		</>
	);
};

export default SetupRealPayments;
