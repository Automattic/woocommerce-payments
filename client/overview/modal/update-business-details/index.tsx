/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';
import strings from './strings';
import { createInterpolateElement } from '@wordpress/element';

interface Props {
	handleModalSubmit: () => void;
	handleModalClose: () => void;
	errorMessages: Array< string >;
	accountStatus: string;
	accountLink: string;
}

const UpdateBusinessDetailsModal = ( {
	handleModalSubmit,
	handleModalClose,
	errorMessages,
	accountStatus,
}: Props ): any => {
	return (
		<Modal
			title={ strings.updateBusinessDetails }
			isDismissible={ true }
			className="update-business-details-modal"
			shouldCloseOnClickOutside={ false }
			onRequestClose={ handleModalClose }
		>
			<p>
				{ accountStatus === 'restricted'
					? strings.restrictedDescription
					: createInterpolateElement(
							strings.restrictedSoonDescription,
							{ '%d': '12th January 2023' }
					  ) }
			</p>


			{ errorMessages.map( ( errorMessage ) => (
				// eslint-disable-next-line react/jsx-key
				<Notice status="warning" isDismissible={ false }>
					{ errorMessage }
				</Notice>
			) ) }

			<div className="wcpay-update-business-details-modal__footer">
				<Button isSecondary onClick={ handleModalClose }>
					{ strings.cancel }
				</Button>
				<Button isPrimary onClick={ handleModalSubmit }>
					{ strings.updateBusinessDetails }
				</Button>
			</div>
		</Modal>
	);
};

export default UpdateBusinessDetailsModal;
