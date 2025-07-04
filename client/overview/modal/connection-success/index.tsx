/**
 * External dependencies
 */
import React from 'react';
import { Modal, Button } from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */
import './style.scss';
import strings from './strings';
import { saveOption } from 'wcpay/data/settings/actions';

/**
 * A modal component displayed when a live account is successfully connected.
 */
export const ConnectionSuccessModal = () => {
	const [ isDismissed, setIsDismissed ] = React.useState(
		wcpaySettings.isConnectionSuccessModalDismissed
	);

	const onDismiss = async () => {
		setIsDismissed( true );

		// Update the option to mark the modal as dismissed.
		saveOption( 'wcpay_connection_success_modal_dismissed', true );
	};

	return (
		<>
			{ ! isDismissed && (
				<Modal
					title={ strings.heading }
					className="woopayments-connection-success-modal"
					isDismissible={ true }
					onRequestClose={ onDismiss }
				>
					<div className="woopayments-connection-success-modal__content">
						{ strings.description }
					</div>
					<div className="woopayments-connection-success-modal__actions">
						<Button
							variant="primary"
							isBusy={ false }
							disabled={ false }
							onClick={ onDismiss }
						>
							{ strings.button }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default ConnectionSuccessModal;
