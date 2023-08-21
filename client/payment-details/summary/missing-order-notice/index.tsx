/** @format **/

/**
 * External dependencies
 */

import React from 'react';
import { Button, CardFooter } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Loadable from 'wcpay/components/loadable';
import { useState } from '@wordpress/element';
import ConfirmationModal from 'wcpay/components/confirmation-modal';

/**
 * Internal dependencies.
 */

declare const window: any;

interface MissingOrderNoticeProps {
	isLoading: boolean;
	amount: string;
}

const MissingOrderNotice: React.FC< MissingOrderNoticeProps > = ( {
	isLoading,
	amount,
} ) => {
	const [ isModalOpen, setIsModalOpen ] = useState( false );

	const handleOnButtonClick = () => {
		setIsModalOpen( true );
	};

	const handleModalCancel = () => {
		setIsModalOpen( false );
	};

	const handleModalConfirmation = () => {
		// TODO: Handle the refund.
	};

	return (
		<>
			<Loadable isLoading={ isLoading } placeholder="">
				<CardFooter className="payment-details-capture-notice">
					<div className="payment-details-capture-notice__section">
						<div className="payment-details-capture-notice__text">
							{ __(
								'This transaction is not connected to order. Investigate this purchase and refund the transaction as needed.',
								'woocommerce-payments'
							) }
						</div>

						<div className="payment-details-capture-notice__button">
							<Button
								isPrimary={ true }
								isSmall={ false }
								onClick={ handleOnButtonClick }
							>
								{ __( 'Refund', 'woocommerce-payments' ) }
							</Button>
						</div>
					</div>
				</CardFooter>
			</Loadable>
			{ isModalOpen && (
				<ConfirmationModal
					title={ __( 'Refund Transaction', 'woocommerce-payments' ) }
					actions={
						<>
							<Button onClick={ handleModalCancel } isSecondary>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
							<Button
								onClick={ handleModalConfirmation }
								isPrimary
							>
								{ __(
									'Refund transaction',
									'woocommerce-payments'
								) }
							</Button>
						</>
					}
					onRequestClose={ handleModalCancel }
				>
					<p>
						{ sprintf(
							__(
								'This will issue a full refund of %s to the customer.',
								'woocommerce-payments'
							),
							amount
						) }
					</p>
					<strong>
						{ __(
							'Select a reason (Optional)',
							'woocommerce-payments'
						) }
					</strong>
				</ConfirmationModal>
			) }
		</>
	);
};

export default MissingOrderNotice;
