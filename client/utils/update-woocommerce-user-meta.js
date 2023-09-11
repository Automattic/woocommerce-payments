/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';

export async function updateWoocommerceUserMeta( newMetaData ) {
	if ( ! newMetaData || Object.keys( newMetaData ).length === 0 ) {
		return;
	}
	const user = await select( 'core' ).getCurrentUser();

	const metaData = Object.keys( newMetaData ).reduce( ( val, key ) => {
		let newValue = newMetaData[ key ];
		if ( typeof newValue !== 'string' ) {
			newValue = JSON.stringify( newValue );
		}
		return {
			...val,
			[ key ]: newValue,
		};
	}, {} );
	const updatedUser = await dispatch( 'core' ).saveUser( {
		id: user.id,
		woocommerce_meta: {
			...user.woocommerce_meta,
			...metaData,
		},
	} );

	if ( undefined === updatedUser ) {
		// Return the encountered error to the caller.
		const error = await select( 'core' ).getLastEntitySaveError(
			'root',
			'user',
			user.id
		);

		return {
			error,
			updatedUser,
		};
	}

	return updatedUser.woocommerce_meta;
}
