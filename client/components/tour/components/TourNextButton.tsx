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

const TourNextButton: React.FC< HTMLAttributes< HTMLButtonElement > > = ( {
	onClick,
	children,
} ) => {
	const { onNextStepButtonClick } = useContext( WcPayTourContext );

	const handleClick = ( e: MouseEvent< HTMLButtonElement > ) => {
		onNextStepButtonClick();
		onClick?.( e );
	};

	return (
		<Button isPrimary onClick={ handleClick }>
			{ children || __( 'Next' ) }
		</Button>
	);
};

export default TourNextButton;
