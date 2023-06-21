/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import CloudDownloadIcon from 'gridicons/dist/cloud-download';

/**
 * Internal dependencies
 */
import './style.scss';

interface DownloadButtonProps {
	isDisabled: boolean;
	onClick: ( event: any ) => void;
}

const DownloadButton: React.FunctionComponent< DownloadButtonProps > = ( {
	isDisabled,
	onClick,
} ) => (
	<Button
		className="woocommerce-table__download-button"
		disabled={ isDisabled }
		onClick={ onClick }
	>
		<CloudDownloadIcon />
		<span className="woocommerce-table__download-button__label">
			{ __( 'Download', 'woocommerce-payments' ) }
		</span>
	</Button>
);

export default DownloadButton;
