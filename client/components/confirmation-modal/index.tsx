/**
 * External dependencies
 */
import React, { PropsWithChildren } from 'react';
import { Modal } from '@wordpress/components';
import classNames from 'classnames';
import { HorizontalRule } from '@wordpress/primitives';

/**
 * Internal dependencies
 */
import './styles.scss';

interface ConfirmationModalInput {
	actions: JSX.Element;
	className?: string;
	title: string;
	onRequestClose: () => void;
	shouldCloseOnClickOutside?: boolean;
}

const ConfirmationModal: React.FunctionComponent< ConfirmationModalInput > = ( {
	children,
	actions,
	className,
	title,
	onRequestClose,
	...props
} ) => (
	<Modal
		className={ classNames( 'wcpay-confirmation-modal', className || '' ) }
		title={ title }
		onRequestClose={ onRequestClose }
		{ ...props }
	>
		{ children }
		<HorizontalRule className="wcpay-confirmation-modal__separator" />
		<div className="wcpay-confirmation-modal__footer">{ actions }</div>
	</Modal>
);

export default ConfirmationModal;
