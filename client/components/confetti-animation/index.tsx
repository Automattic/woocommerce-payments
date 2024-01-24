/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import confetti from 'canvas-confetti';

const defaultColors = [ '#889BF2', '#C3CDF9', '#6079ED' ];

const randomInRange = ( min: number, max: number ): number =>
	Math.floor( Math.random() * ( max - min ) + min );

// Use a rectangle instead of an square on supported browsers.
const rectangle =
	typeof Path2D === 'function' && typeof DOMMatrix === 'function'
		? confetti.shapeFromPath( {
				path: 'M0,0 L2,0 L2,1 L0,1 Z',
		  } )
		: 'square';

// Adjust particle amount based on screen size.
const particleLength = ( window.innerWidth + window.innerHeight ) / 50;

const fireConfetti = ( colors: string[] ) => {
	const defaults = {
		spread: 360,
		particleCount: 1,
		startVelocity: 0,
		zIndex: 1000000,
	};

	for ( let i = 0; i < particleLength; i++ ) {
		confetti( {
			...defaults,
			colors: [ colors[ randomInRange( 0, colors.length ) ] ],
			origin: {
				x: Math.random(),
				y: Math.random() * 0.999 - 0.2,
			},
			drift: randomInRange( -2, 2 ),
			shapes: [ 'circle' ],
		} );
		confetti( {
			...defaults,
			colors: [ colors[ randomInRange( 0, colors.length ) ] ],
			origin: {
				x: Math.random(),
				y: Math.random() * 0.999 - 0.2,
			},
			shapes: [ rectangle ],
			drift: randomInRange( -2, 2 ),
			scalar: randomInRange( 2, 4 ),
		} );
	}
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
