
/**
 * External dependencies
 */
import React, { useCallback,  useEffect } from 'react';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';
import {Button, Modal} from "@wordpress/components";

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';

const PlatformCheckoutIframeModal = () => {
	return (
		// <Modal
		// 	focusOnMount //focus on the first element in the modal
		// 	shouldCloseOnEsc
		// 	shouldCloseOnClickOutside
		// 	overlayClassName="my-extra-modal-overlay-class"
		// 	title="This is my modal"
		// 	onRequestClose={ e => console.log("Closing modal") }
		// >
		// 	<p>Thanks for using this modal</p>
			<Button isSecondary >
				My custom close button
			</Button>
		// </Modal>
	);
}

export default PlatformCheckoutIframeModal;
