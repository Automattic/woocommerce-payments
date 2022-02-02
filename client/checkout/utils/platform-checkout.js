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

	const validateEmail = ( value ) => {
		const input = document.createElement( 'input' );
		input.type = 'email';
		input.required = true;
		input.value = value;

		return input.checkValidity() || false;
	};

	platformCheckoutEmailInput.addEventListener( 'input', ( e ) => {
		const input = e.currentTarget.value;

		clearTimeout( timer );
		spinner.remove();

		timer = setTimeout( () => {
			if ( validateEmail( input ) ) {
				platformCheckoutLocateUser( input );
			}
		}, waitTime );
	} );
};
