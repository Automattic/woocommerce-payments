/**
 * External dependencies
 */
import React, {
	useCallback,
	useContext,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import classnames from 'classnames';
import { Button, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WcPayTourContext from '../context';
import { TourCoordinates, TourOptionPosition } from '../interfaces';
import { calculateCoordinates } from '../utils';

interface TourStepProps {
	selector: string;
	position: TourOptionPosition;
}

const TourStep: React.FC< TourStepProps > = ( {
	children,
	selector,
	position,
} ) => {
	const containerRef = useRef< HTMLDivElement >( null );
	const [ id, setId ] = useState< string >();
	const [ coordinates, setCoordinates ] = useState< TourCoordinates >();

	const {
		currentStep,
		onTourEnd,
		registerStep,
		onCloseButtonClick,
	} = useContext( WcPayTourContext );

	const isActive = useMemo( () => currentStep === id, [ currentStep, id ] );

	useEffect( () => {
		setId( registerStep( selector ) );
	}, [ selector, registerStep ] );

	const updateModalPosition = useCallback( () => {
		if ( ! isActive ) return;

		try {
			if ( ! selector ) {
				throw new Error(
					'No selector given for the current tour step.'
				);
			}

			const element = document.querySelector( selector );
			const container = containerRef.current;

			if ( ! element || ! container ) {
				throw new Error(
					'No reference element or tour modal element found.'
				);
			}

			const elementRect = element.getBoundingClientRect();
			const containerRect = container.getBoundingClientRect();

			const updatedCoordinates = calculateCoordinates(
				elementRect,
				containerRect,
				position
			);

			setCoordinates( updatedCoordinates );
		} catch ( e ) {
			setCoordinates( { x: 0, y: 0, sticky: true } );
		}
	}, [ selector, isActive, position ] );

	useLayoutEffect( () => {
		let observer: ResizeObserver;

		if ( 'ResizeObserver' in window ) {
			observer = new ResizeObserver( updateModalPosition );
			observer.observe( document.body );
		}

		updateModalPosition();

		return () => {
			if ( observer ) {
				observer.unobserve( document.body );
			}
		};
	}, [ updateModalPosition ] );

	useLayoutEffect( () => {
		if ( ! coordinates || ! isActive ) return;

		const { x, y, scrollPadding } = coordinates;
		const [ paddingX, paddingY ] = scrollPadding || [ 0, 0 ];

		const left = x - paddingX;
		const top = y - paddingY - 50;

		window.scrollTo( { left, top } );
	}, [ coordinates, isActive ] );

	const handleCloseButtonClick = () => {
		onCloseButtonClick?.();
		onTourEnd();
	};

	if ( ! isActive ) return null;

	return (
		<div
			ref={ containerRef }
			className={ classnames( 'tour-modal', {
				'tour-modal--arrow': coordinates?.arrow,
				[ `tour-modal--arrow-${ coordinates?.arrow }` ]: coordinates?.arrow,
				'tour-modal--sticky': coordinates?.sticky,
			} ) }
			style={
				coordinates ? { top: coordinates.y, left: coordinates.x } : {}
			}
		>
			<Button
				onClick={ handleCloseButtonClick }
				className="tour-modal__close-button"
				aria-label={ __( 'Close tour modal' ) }
			>
				<Icon icon="no-alt" />
			</Button>

			{ children }
		</div>
	);
};

export default TourStep;
