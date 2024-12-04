export function initializeUpeAppearanceEditor(
	initialAppearance,
	elementsLocation,
	api,
	sections
) {
	if ( window.initializeUpeAppearanceEditor ) {
		window.initializeUpeAppearanceEditor(
			initialAppearance,
			elementsLocation,
			api,
			sections
		);
	}
}

export function registerElementsComponent( elements, elementsLocation ) {
	if ( window.registerElementsComponent ) {
		window.registerElementsComponent( elements, elementsLocation );
	}
}
