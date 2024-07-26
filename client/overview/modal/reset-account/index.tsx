/**
 * External dependencies
 */
import React from 'react';
import { Button, CardDivider, Modal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import strings from './strings';

interface Props {
	isVisible: boolean;
	onSubmit: () => void;
	onDismiss: () => void;
}

const ResetAccountModal: React.FC< Props > = ( props: Props ) => {
	const { isVisible, onDismiss, onSubmit } = props;
	if ( ! isVisible ) return null;

	return (
		<Modal
			title={ strings.title }
			className="wcpay-reset-account-modal"
			onRequestClose={ onDismiss }
		>
			<p className="wcpay-reset-account-modal__headline">
				{ strings.description }
			</p>
			<div className="wcpay-reset-account-modal__content">
				<b>{ strings.beforeContinue }</b>
				<ol>
					<li>{ strings.step1 }</li>
					<li>{ strings.step2 }</li>
					<li>{ strings.step3 }</li>
				</ol>
				<CardDivider />
				<b>{ strings.confirmation }</b>
			</div>
			<div className="wcpay-reset-account-modal__footer">
				<Button variant="tertiary" onClick={ onDismiss }>
					{ strings.cancel }
				</Button>
				<Button
					variant="primary"
					isDestructive={ true }
					onClick={ onSubmit }
				>
					{ strings.reset }
				</Button>
			</div>
		</Modal>
	);
};

export default ResetAccountModal;
