function createFormData( obj, subKeyStr = '', formData ) {
	for ( const key in obj ) {
		const value = obj[ key ];
		const subKeyStrTrans = subKeyStr ? subKeyStr + '[' + key + ']' : key;

		if ( typeof value === 'string' || typeof value === 'number' ) {
			formData.append( subKeyStrTrans, value );
		} else if ( typeof value === 'object' ) {
			createFormData( value, subKeyStrTrans, formData );
		}
	}

	return formData;
}

export default async function request( url, args, options ) {
	const data = createFormData( args, '', new FormData() );

	const response = await fetch( url, {
		method: 'POST',
		body: data,
		...options,
	} );

	return await response.json();
}
