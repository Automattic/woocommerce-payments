/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Modal } from 'wcpay/components/wp-components-wrapped/components/modal';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import VatForm from '../form';
import { VatFormOnCompleted } from '../types';

const VatFormModal = ( {
	isModalOpen,
	setModalOpen,
	onCompleted,
}: {
	isModalOpen: boolean;
	setModalOpen: ( value: boolean ) => void;
	onCompleted: VatFormOnCompleted;
} ): JSX.Element | null => {
	return isModalOpen ? (
		<Modal
			title={ __( 'Set your tax details', 'woocommerce-payments' ) }
			onRequestClose={ () => setModalOpen( false ) }
		>
			<VatForm onCompleted={ onCompleted } />
		</Modal>
	) : null;
};

export default VatFormModal;
