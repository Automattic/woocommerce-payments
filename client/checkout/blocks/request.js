export default async function request( url, args ) {
	const data = new FormData();
	for ( const key in args ) {
		data.append( key, args[ key ] );
	}

	const response = await fetch( url, {
		method: 'POST',
		body: data,
	} );

	return await response.json();
}
