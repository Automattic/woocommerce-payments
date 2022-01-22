// Check whether user is a valid Platform Checkout user
/* export const handlePlatformCheckoutEmailInput = ( container, field ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailContainer = document.querySelector( container );

	const platformCheckoutLocateUser = () => {
		platformCheckoutEmailContainer.classList.add( 'is-loading' );

		// Placeholder to simulate request. Replace with request to Platform Checkout email verification endpoint.
		setTimeout( () => {
			platformCheckoutEmailContainer.classList.remove( 'is-loading' );
		}, 3000 );
	};

	const platformCheckoutEmailInput = document.querySelector( field );

	platformCheckoutEmailInput.addEventListener( 'input', ( e ) => {
		const input = e.currentTarget.value;

		clearTimeout( timer );
		platformCheckoutEmailContainer.classList.remove( 'is-loading' );

		timer = setTimeout( () => {
			platformCheckoutLocateUser( input );
		}, waitTime );
	} );
}; */
export const handlePlatformCheckoutEmailInput = ( field ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = document.querySelector( field );
	const spinner = document.createElement( 'div' );
	const parentDiv = platformCheckoutEmailInput.parentNode;
	spinner.classList.add( 'wc-block-components-spinner' );

	const platformCheckoutLocateUser = () => {
		parentDiv.insertBefore( spinner, platformCheckoutEmailInput );

		// Placeholder to simulate request. Replace with request to Platform Checkout email verification endpoint.
		setTimeout( () => {
			spinner.remove();
		}, 3000 );
	};

	platformCheckoutEmailInput.addEventListener( 'input', ( e ) => {
		const input = e.currentTarget.value;

		clearTimeout( timer );
		spinner.remove();

		timer = setTimeout( () => {
			platformCheckoutLocateUser( input );
		}, waitTime );
	} );
};
