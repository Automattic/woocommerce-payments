/**
 * External dependencies
 */
import React, { useState, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

interface CopyButtonProps {
	textToCopy: string;
	label: string;
}

export const CopyButton: React.FC< CopyButtonProps > = ( {
	textToCopy,
	label,
} ) => {
	// useRef() is used to store the timer reference for the setTimeout() function.
	const timerRef = useRef< NodeJS.Timeout | null >( null );

	// useEffect() is used to clear the timer reference when the component is unmounted.
	useEffect( () => {
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [] );

	const [ copied, setCopied ] = useState( false );

	const copyToClipboard = () => {
		navigator.clipboard.writeText( textToCopy );
		setCopied( true );
		timerRef.current = setTimeout( () => {
			setCopied( false );
		}, 2000 );
	};

	return (
		<button
			type="button"
			className={ classNames( 'woopayments-copy-button', {
				'state--copied': copied,
			} ) }
			aria-label={ label }
			title={ __( 'Copy to clipboard', 'woocommerce-payments' ) }
			onClick={ copyToClipboard }
		>
			<i></i>
		</button>
	);
};
