/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './style.scss';

interface CopyButtonProps {
	/**
	 * The text to copy to the clipboard.
	 */
	textToCopy: string;

	/**
	 * The label for the button. Also used as the aria-label.
	 */
	label: string;
}

export const CopyButton: React.FC< CopyButtonProps > = ( {
	textToCopy,
	label,
} ) => {
	const [ copied, setCopied ] = useState( false );

	const copyToClipboard = () => {
		navigator.clipboard.writeText( textToCopy );
		setCopied( true );
	};

	return (
		<button
			type="button"
			className={ clsx( 'woopayments-copy-button', {
				'state--copied': copied,
			} ) }
			aria-label={ label }
			title={ __( 'Copy to clipboard', 'woocommerce-payments' ) }
			onClick={ copyToClipboard }
			onAnimationEnd={ () => setCopied( false ) }
		>
			<i></i>
		</button>
	);
};
