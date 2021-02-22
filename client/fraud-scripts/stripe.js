const src = 'https://js.stripe.com/v3';

export default () => {
	if ( ! document.querySelector( `[src^="${ src }"]` ) ) {
		const script = document.createElement( 'script' );
		script.src = src;
		script.async = true;
		document.body.appendChild( script );
	}
};
