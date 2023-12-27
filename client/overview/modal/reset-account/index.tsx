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
			title={ 'Reset account' }
			className="wcpay-reset-account-modal"
			onRequestClose={ onDismiss }
		>
			<div className="wcpay-reset-account-modal__content">
				<p>{ strings.description }</p>
				<p>
					<b>{ strings.beforeContinue }</b>
				</p>
				<ol>
					<li>{ strings.step1 }</li>
				</ol>
				<CardDivider />
				<ol start={ 2 }>
					<li>{ strings.step2 }</li>
				</ol>
				<CardDivider />
				<ol start={ 3 }>
					<li>{ strings.step3 }</li>
				</ol>
				<CardDivider />
				<p>{ strings.confirmation }</p>
			</div>
			<div className="wcpay-reset-account-modal__footer">
				<Button variant={ 'secondary' } onClick={ onDismiss }>
					{ strings.cancel }
				</Button>
				<Button
					variant={ 'primary' }
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
