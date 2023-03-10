/**
 * Internal dependencies
 */
import {
	TourCoordinates,
	TourOptionPosition,
	TourOptionAbsolutePosition,
	TourOptionRelativePosition,
} from './interfaces';

const roundCoordinates = ( { x, y, ...rest }: TourCoordinates ) => ( {
	...rest,
	x: Math.floor( x ),
	y: Math.floor( y ),
} );

const absoluteCoordinatesMapping: Record<
	TourOptionAbsolutePosition,
	( element: DOMRect, container: DOMRect ) => TourCoordinates
> = {
	top: ( element: DOMRect, container: DOMRect ) => ( {
		x:
			window.scrollX +
			element.left +
			element.width / 2 -
			container.width / 2,
		y: window.scrollY + element.top - container.height - 16,
		scrollPadding: [ 0, container.height ],
		arrow: 'bottom',
	} ),
	bottom: ( element: DOMRect, container: DOMRect ) => ( {
		x:
			window.scrollX +
			element.left +
			element.width / 2 -
			container.width / 2,
		y: window.scrollY + element.top + element.height + 16,
		scrollPadding: [ 0, container.height ],
		arrow: 'top',
	} ),
	left: ( element: DOMRect, container: DOMRect ) => ( {
		x: window.scrollX + element.left - container.width - 16,
		y:
			window.scrollY +
			element.top -
			container.height / 2 +
			element.height / 2,
		scrollPadding: [ 0, container.height ],
		arrow: 'right',
	} ),
	right: ( element: DOMRect, container: DOMRect ) => ( {
		x: window.scrollX + element.left + element.width + 16,
		y:
			window.scrollY +
			element.top -
			container.height / 2 +
			element.height / 2,
		scrollPadding: [ 0, container.height ],
		arrow: 'left',
	} ),
};

const calculateAbsoluteCoordinates = (
	element: DOMRect,
	container: DOMRect,
	position: TourOptionPosition = 'top'
) => {
	let coordinates = absoluteCoordinatesMapping[
		position as keyof typeof absoluteCoordinatesMapping
	]( element, container );

	if (
		position === 'right' &&
		coordinates.x + container.width > document.documentElement.clientWidth
	) {
		coordinates = absoluteCoordinatesMapping.left( element, container );
	}

	return roundCoordinates( coordinates );
};

const calculateRelativeCoordinates = (
	element: DOMRect,
	container: DOMRect,
	position: TourOptionRelativePosition
): TourCoordinates => {
	let y = 0;
	let x = 0;

	if ( position.bottom ) {
		y = element.height - container.height - position.bottom + 32;
	} else if ( position.top ) {
		y = position.top + 32;
	}

	if ( position.left ) {
		x = element.left + position.left;
	} else if ( position.right ) {
		x = element.right - container.width - position.right;
	}

	return roundCoordinates( { x, y } );
};

export const calculateCoordinates = (
	element: DOMRect,
	container: DOMRect,
	position: TourOptionPosition = 'top'
): TourCoordinates => {
	if ( typeof position !== 'string' ) {
		return calculateRelativeCoordinates( element, container, position );
	}

	return calculateAbsoluteCoordinates( element, container, position );
};
