/* eslint-disable */
/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';

/**
 * Internal dependencies.
 */

interface Props {
	children: ( amount: number, easterEggMode: boolean ) => JSX.Element;
}

const EasterEgg = ( { children }: Props ) => {
	let [ count, setCount ] = useState( 0 );
	let [ amount, setAmount ] = useState( 0 );
	const [ easterEggMode, setMode ] = useState( false );

	useEffect( () => {
		if ( count === 4 ) {
			setMode( true );
		}
	}, [ count ] );

	useEffect( () => {
		const handleScroll = () => {
			if ( easterEggMode ) setAmount( Math.pow( window.scrollY, 4.1 ) );
		};

		window.addEventListener( 'scroll', handleScroll );

		return () => {
			window.removeEventListener( 'scroll', handleScroll );
		};
	}, [ easterEggMode, setAmount ] );

	const handleClick = ( e: any ) => {
		setCount( ++count );
		var d = document.createElement( 'div' );
		d.className = 'clickEffect';
		d.style.top = e.clientY + 'px';
		d.style.left = e.clientX + 'px';
		document.body.appendChild( d );
		d.addEventListener(
			'animationend',
			function () {
				document.body.removeChild( d );
			}.bind( this )
		);
	};

	return (
		<>
			<div
				onClick={ handleClick }
				className={ `jackpot wcpay-deposits-information-block ${
					easterEggMode && 'jackpot-enabled'
				}` }
			>
				{ children( amount, easterEggMode ) }{ ' ' }
				{ easterEggMode && <p>Your next deposit will be 👆</p> }
			</div>
			{ easterEggMode && (
				<>
					<img
						className="jackpot-gif"
						src="https://media.giphy.com/media/3G7Dsjdupy1Gd5PWrF/giphy.gif"
					/>
					<p className="jackpot-message">You deserve it!</p>
				</>
			) }
		</>
	);
};

export default EasterEgg;
