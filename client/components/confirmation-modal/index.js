/**
 * External dependencies
 */
import React from 'react';
import { Modal } from '@wordpress/components';
import classNames from 'classnames';
import { HorizontalRule } from '@wordpress/primitives';

/**
 * Internal dependencies
 */
import './styles.scss';

const ConfirmationModal = ( { children, actions, className, ...props } ) => (
	<Modal
		className={ classNames( 'wcpay-confirmation-modal', className ) }
		{ ...props }
	>
		{ children }
		<HorizontalRule className="wcpay-confirmation-modal__separator" />
		<div className="wcpay-confirmation-modal__footer">{ actions }</div>
	</Modal>
);

export default ConfirmationModal;
