/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';

interface GenericConfirmationModalProps {
	title: string;
	confirmButtonText: string;
	cancelButtonText: string;
	confirmationMessage: string;
	onConfirm: () => void;
	onCancel: () => void;
	confirmButtonLink: string;
	cancelButtonLink: string;
}

const GenericConfirmationModal: React.FunctionComponent< GenericConfirmationModalProps > = ( {
	title,
	confirmButtonText,
	cancelButtonText,
	confirmationMessage,
	onConfirm,
	onCancel,
	confirmButtonLink,
	cancelButtonLink,
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

	const interpolatedMessage = interpolateComponents( {
		mixedString: confirmationMessage,
		components: {
			confirmButtonLink: (
				<a
					target="_blank"
					href={ confirmButtonLink }
					rel="noopener noreferrer"
				>
					{ confirmButtonText }
				</a>
			),
			cancelButtonLink: (
				<a
					target="_blank"
					href={ cancelButtonLink }
					rel="noopener noreferrer"
				>
					{ cancelButtonText }
				</a>
			),
		},
	} );

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
					<p>{ interpolatedMessage }</p>
				</ConfirmationModal>
			) }
		</>
	);
};

export default GenericConfirmationModal;
