/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import { UpeAppearanceEditor } from './UpeAppearanceEditor';
import { debounce } from 'lodash';

export function initializeUpeAppearanceEditor(
	initialAppearance,
	elementsLocation,
	api,
	sections
) {
	const mountElementID = `upe-appearance-editor-root-${ elementsLocation }`;
	if ( document.getElementById( mountElementID ) ) {
		return;
	}

	const applyAppearance = debounce(
		( appearance ) =>
			updateElementsAppearance( elementsLocation, appearance ),
		300
	);

	const mountElement = document.createElement( 'div' );
	mountElement.id = mountElementID;
	document.body.appendChild( mountElement );

	const root = ReactDOM.createRoot( mountElement );
	root.render(
		<UpeAppearanceEditor
			initialAppearance={ initialAppearance }
			elementsLocation={ elementsLocation }
			api={ api }
			applyAppearance={ applyAppearance }
			sections={ sections }
		/>
	);
}

const elementsComponents = {};

export function registerElementsComponent( elements, elementsLocation ) {
	if ( ! elementsComponents[ elementsLocation ] ) {
		elementsComponents[ elementsLocation ] = [];
	}
	elementsComponents[ elementsLocation ].push( elements );
}

export function updateElementsAppearance( elementsLocation, appearance ) {
	if ( ! elementsComponents[ elementsLocation ] ) {
		return;
	}

	elementsComponents[ elementsLocation ].forEach( ( elements ) => {
		elements.update( { appearance } );
	} );
}
