/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button, Modal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { trackAccountReset } from 'onboarding/tracking';
import './style.scss';
import strings from './strings';

const ResetAccountModal: React.FC = () => {
	const [ modalVisible, setModalVisible ] = useState( true );

	const handleReset = () => {
		trackAccountReset();

		// // Note: we don't need to update the option here because it will be handled upon redirect to the connect URL.
		// window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
		// 	collect_payout_requirements: true,
		// } );
	};

	if ( ! modalVisible ) return null;

	return (
		<Modal
			title={ 'Reset account' }
			className="wcpay-reset-account-modal"
			onRequestClose={ () => setModalVisible( false ) }
		>
			<div className="wcpay-reset-account-modal__content">
				<p>{ strings.description }</p>
				<p>
					<b>{ strings.beforeContinue }</b>
				</p>
				<ol>
					<li>{ strings.step1 }</li>
					<li>{ strings.step2 }</li>
					<li>{ strings.step3 }</li>
				</ol>
				<p>{ strings.confirmation }</p>
			</div>
			<div className="wcpay-reset-account-modal__footer">
				<Button
					variant={ 'secondary' }
					onClick={ () => setModalVisible( false ) }
				>
					{ strings.cancel }
				</Button>
				<Button
					variant={ 'primary' }
					isDestructive={ true }
					onClick={ handleReset }
				>
					{ strings.reset }
				</Button>
			</div>
		</Modal>
	);
};

export default ResetAccountModal;
