// Check whether user is a valid Platform Checkout user
export const handlePlatformCheckoutEmailInput = ( container, field ) => {
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
};
