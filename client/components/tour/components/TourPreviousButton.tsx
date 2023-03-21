/**
 * External dependencies
 */
import React, { HTMLAttributes, MouseEvent, useContext } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WcPayTourContext from '../context';

const TourPreviousButton: React.FC< HTMLAttributes< HTMLButtonElement > > = ( {
	onClick,
	children,
} ) => {
	const { onPreviousStepButtonClick } = useContext( WcPayTourContext );

	const handleClick = ( e: MouseEvent< HTMLButtonElement > ) => {
		onPreviousStepButtonClick();
		onClick?.( e );
	};

	return (
		<Button isSecondary onClick={ handleClick }>
			{ children || __( 'Previous' ) }
		</Button>
	);
};

export default TourPreviousButton;
