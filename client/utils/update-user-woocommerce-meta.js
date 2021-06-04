/* eslint-disable camelcase */
/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';

export async function updateUserWoocommerceMeta( newMetaData ) {
	if ( ! newMetaData || 0 === Object.keys( newMetaData ).length ) {
		return;
	}
	const user = await select( 'core' ).getCurrentUser();

	const metaData = Object.keys( newMetaData ).reduce( ( val, key ) => {
		let newValue = newMetaData[ key ];
		if ( 'string' !== typeof newValue ) {
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
