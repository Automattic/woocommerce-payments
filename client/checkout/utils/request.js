export default async function request( url, args, options ) {
	const data = new FormData();
	for ( const key in args ) {
		data.append( key, args[ key ] );
	}

	const response = await fetch( url, {
		method: 'POST',
		body: data,
		...options,
	} );

	return await response.json();
}
