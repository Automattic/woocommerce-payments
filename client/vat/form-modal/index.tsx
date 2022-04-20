/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import VatForm from '../form';

const VatFormModal = ( {
	isModalOpen,
	setModalOpen,
}: {
	isModalOpen: boolean;
	setModalOpen: ( value: boolean ) => void;
} ): JSX.Element | null => {
	return isModalOpen ? (
		<Modal
			title={ __( 'VAT details', 'woocommerce-payments' ) }
			isDismissible={ false }
			shouldCloseOnClickOutside={ true }
			onRequestClose={ () => setModalOpen( false ) }
		>
			<VatForm />
		</Modal>
	) : null;
};

export default VatFormModal;
