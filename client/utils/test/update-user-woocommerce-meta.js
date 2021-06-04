/* eslint-disable camelcase */
/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { updateUserWoocommerceMeta } from '../update-user-woocommerce-meta';

jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn(),
	select: jest.fn(),
} ) );

describe( 'updateUserWoocommerceMeta', () => {
	let getCurrentUser, saveUser, getLastEntitySaveError;
	beforeEach( () => {
		getCurrentUser = jest
			.fn()
			.mockReturnValue( { id: 2, woocommerce_meta: { data: 'test' } } );
		getLastEntitySaveError = jest.fn();
		select.mockReturnValue( {
			getCurrentUser,
			getLastEntitySaveError,
		} );
		saveUser = jest.fn();
		dispatch.mockReturnValue( {
			saveUser,
		} );
	} );
	test( 'it should do nothing when passed in meta data is empty', async () => {
		const data = await updateUserWoocommerceMeta( {} );
		expect( data ).toBeUndefined();
	} );

	test( 'it retrieve the current user first', async () => {
		await updateUserWoocommerceMeta( { newvalue: '' } );
		expect( getCurrentUser ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'it call saveUser with user id and new and old meta data', async () => {
		await updateUserWoocommerceMeta( { newvalue: 'test' } );
		expect( saveUser ).toHaveBeenCalledWith( {
			id: 2,
			woocommerce_meta: {
				data: 'test',
				newvalue: 'test',
			},
		} );
	} );

	test( 'it should stringify non string values', async () => {
		await updateUserWoocommerceMeta( {
			newvalue: 'test',
			anumber: 123,
		} );
		expect( saveUser ).toHaveBeenCalledWith( {
			id: 2,
			woocommerce_meta: {
				data: 'test',
				newvalue: 'test',
				anumber: '123',
			},
		} );
	} );

	test( 'it call getLastEntitySAveError and return the error if saveUser return undefined', async () => {
		saveUser.mockReturnValue( Promise.resolve( undefined ) );
		getLastEntitySaveError.mockReturnValue( Promise.resolve( 'a error' ) );
		const data = await updateUserWoocommerceMeta( {
			newvalue: 'test',
		} );
		expect( getLastEntitySaveError ).toHaveBeenCalled();
		expect( data.error ).toEqual( 'a error' );
	} );

	test( 'it should return the users woocommerce_meta after successful save', async () => {
		saveUser.mockReturnValue(
			Promise.resolve( {
				woocommerce_meta: {
					updated: 'updated',
				},
			} )
		);
		const data = await updateUserWoocommerceMeta( {
			newvalue: 'test',
		} );
		expect( data.updated ).toEqual( 'updated' );
	} );
} );
