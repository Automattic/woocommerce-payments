/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';

interface OrderStatusConfirmationModalProps {
	title: string;
	confirmButtonText: string;
	cancelButtonText: string;
	confirmationMessage: any;
	onConfirm: () => void;
	onCancel: () => void;
}

const OrderStatusConfirmationModal: React.FunctionComponent< OrderStatusConfirmationModalProps > = ( {
	title,
	confirmButtonText,
	cancelButtonText,
	confirmationMessage,
	onConfirm,
	onCancel,
} ) => {
	const [ isOpen, setIsOpen ] = useState( true );

	const closeModal = (): void => {
		setIsOpen( false );
	};

	const handleConfirm = (): void => {
		onConfirm();
		closeModal();
	};

	const handleCancel = (): void => {
		onCancel();
		closeModal();
	};

	const buttonContent = (
		<>
			<Button variant="secondary" onClick={ handleCancel }>
				{ cancelButtonText }
			</Button>
			<Button variant="primary" onClick={ handleConfirm }>
				{ confirmButtonText }
			</Button>
		</>
	);

	return (
		<>
			{ isOpen && (
				<ConfirmationModal
					title={ title }
					isDismissible={ false }
					className="generic-confirmation-modal"
					actions={ buttonContent }
					onRequestClose={ () => false }
				>
					<p>{ confirmationMessage }</p>
				</ConfirmationModal>
			) }
		</>
	);
};

export default OrderStatusConfirmationModal;
