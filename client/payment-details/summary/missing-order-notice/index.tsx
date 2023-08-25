/** @format **/

/**
 * External dependencies
 */

import React from 'react';
import { Button, RadioControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Loadable from 'wcpay/components/loadable';
import { useState } from '@wordpress/element';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */

import './style.scss';
import CardNotice from 'wcpay/components/card-notice';

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

	const [ reason, setReason ] = useState< string | null >( null );

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
				<CardNotice
					actions={
						<Button
							isPrimary={ true }
							isSmall={ false }
							onClick={ handleOnButtonClick }
						>
							{ __( 'Refund', 'woocommerce-payments' ) }
						</Button>
					}
				>
					{ __(
						'This transaction is not connected to order. Investigate this purchase and refund the transaction as needed.',
						'woocommerce-payments'
					) }
				</CardNotice>
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
						{ interpolateComponents( {
							mixedString: sprintf(
								__(
									'This will issue a full refund of {{strong}}%s{{/strong}} to the customer.',
									'woocommerce-payments'
								),
								amount
							),
							components: {
								strong: <strong />,
							},
						} ) }
					</p>
					<RadioControl
						className="missing-order-notice__modal__reason"
						label={ interpolateComponents( {
							mixedString: __(
								'{{strong}}Select a reason (Optional){{/strong}}',
								'woocommerce-payments'
							),
							components: {
								strong: <strong />,
							},
						} ) }
						selected={ reason }
						options={ [
							{
								label: __(
									'Duplicate order',
									'woocommerce-payments'
								),
								value: 'duplicate_order',
							},
							{
								label: __(
									'Fraudulent',
									'woocommerce-payments'
								),
								value: 'fraudulent',
							},
							{
								label: __(
									'Requested by customer',
									'woocommerce-payments'
								),
								value: 'requested_by_customer',
							},
							{
								label: __( 'Other', 'woocommerce-payments' ),
								value: 'other',
							},
						] }
						onChange={ ( value: string ) => setReason( value ) }
					/>
				</ConfirmationModal>
			) }
		</>
	);
};

export default MissingOrderNotice;
