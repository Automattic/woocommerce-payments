/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

export const renderSetupLivePaymentsModal = async () => {
	const { default: SetupLivePaymentsModal } = await import(
		'wcpay/components/sandbox-mode-switch-to-live-notice/modal'
	);
	const container = document.createElement( 'div' );
	container.id = 'wcpay-golivemodal-container';
	document.body.appendChild( container );
	const root = createRoot( container );
	const closeModal = () => {
		root.unmount();
		container.remove();
	};

	root.render(
		React.createElement( SetupLivePaymentsModal, {
			from: 'WCPAY_GO_LIVE_TASK',
			source: 'wcpay-go-live-task',
			onClose: closeModal,
		} )
	);
};
