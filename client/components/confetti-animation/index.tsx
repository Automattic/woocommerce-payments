/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import confetti from 'canvas-confetti';

const defaultColors = [ '#889BF2', '#C3CDF9', '#6079ED' ];

const fireConfetti = ( colors: string[] ) => {
	const defaults = {
		origin: { y: 0.3 },
		spread: 360,
		zIndex: 1000000,
		colors,
	};

	const rectangle = confetti.shapeFromPath( {
		path: 'M0,0 L2,0 L2,1 L0,1 Z',
	} );

	confetti( {
		...defaults,
		particleCount: 20,
		shapes: [ rectangle ],
		scalar: 4,
		startVelocity: 60,
	} );
	confetti( {
		...defaults,
		particleCount: 20,
		shapes: [ rectangle ],
		scalar: 2,
		startVelocity: 40,
	} );
	confetti( {
		...defaults,
		particleCount: 40,
		shapes: [ 'circle' ],
		startVelocity: 20,
	} );
};

interface Props {
	trigger?: boolean;
	delay?: number;
	colors?: string[];
}

const ConfettiAnimation: React.FC< Props > = ( {
	trigger = true,
	delay = 250,
	colors = defaultColors,
} ) => {
	useEffect( () => {
		if ( trigger ) {
			setTimeout( () => fireConfetti( colors ), delay );
		}
	}, [ trigger, delay, colors ] );

	return null;
};

export default ConfettiAnimation;
