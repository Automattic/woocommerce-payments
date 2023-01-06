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

interface UpdateBusinessDetailsModalProps {
	isModalOpen: boolean;
	setModalOpen: any; // TODO: figure out the type for this
	errorReasons: Array< string >;
	accountStatus: string;
}

const UpdateBusinessDetailsModal = ( {
	isModalOpen,
	setModalOpen,
	errorReasons,
	accountStatus,
}: UpdateBusinessDetailsModalProps ): any => {
	const onClose = () => {
		setModalOpen( false );
	};

	const onSubmit = () => {
		return true;
	};

	const generateNotice = ( errorMessage: string ): JSX.Element => {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ errorMessage }
			</Notice>
		);
	};

	return (
		isModalOpen && (
			<Modal
				title={ strings.updateBusinessDetails }
				isDismissible={ true }
				className="update-business-details-modal"
				shouldCloseOnClickOutside={ false }
				onRequestClose={ onClose }
			>
				<p>
					{ accountStatus === 'restricted'
						? strings.restrictedDescription
						: createInterpolateElement(
								strings.restrictedSoonDescription,
								{ '%d': '12th January 2023' }
						  ) }
				</p>

				{ errorReasons.forEach( generateNotice ) }

				<div className="wcpay-update-business-details-modal__footer">
					<Button isSecondary onClick={ onClose }>
						{ strings.cancel }
					</Button>
					<Button isPrimary onClick={ onSubmit }>
						{ strings.updateBusinessDetails }
					</Button>
				</div>
			</Modal>
		)
	);
};

export default UpdateBusinessDetailsModal;
