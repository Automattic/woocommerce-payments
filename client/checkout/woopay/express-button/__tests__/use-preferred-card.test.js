/**
 * External dependencies
 */
import { renderHook, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import usePreferredCard from '../use-preferred-card';
import {
	getCachedPreferredCard,
	setCachedPreferredCard,
} from '../preferred-card-utils';
import { fetchPreferredCard } from '../preferred-card-fetch';

jest.mock( '../preferred-card-utils', () => ( {
	...jest.requireActual( '../preferred-card-utils' ),
	getCachedPreferredCard: jest.fn().mockReturnValue( null ),
	setCachedPreferredCard: jest.fn(),
} ) );

jest.mock( '../preferred-card-fetch', () => ( {
	fetchPreferredCard: jest.fn().mockResolvedValue( null ),
} ) );

describe( 'usePreferredCard', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		getCachedPreferredCard.mockReturnValue( null );
		fetchPreferredCard.mockResolvedValue( null );
	} );

	test( 'initializes from localStorage cache', async () => {
		const cached = { brand: 'visa', last4: '4242' };
		getCachedPreferredCard.mockReturnValue( cached );

		const { result } = renderHook( () => usePreferredCard() );

		expect( result.current ).toEqual( cached );

		// Wait for the useEffect fetch to settle.
		await waitFor( () => {
			expect( fetchPreferredCard ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	test( 'fetches from Connect iframe on mount', async () => {
		renderHook( () => usePreferredCard() );

		await waitFor( () => {
			expect( fetchPreferredCard ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	test( 'updates state and cache when fetch succeeds', async () => {
		const card = { brand: 'mastercard', last4: '5555' };
		fetchPreferredCard.mockResolvedValue( card );

		const { result } = renderHook( () => usePreferredCard() );

		await waitFor( () => {
			expect( result.current ).toEqual( card );
		} );
		expect( setCachedPreferredCard ).toHaveBeenCalledWith( card );
	} );

	test( 'keeps cached state when fetch fails', async () => {
		const cached = { brand: 'visa', last4: '4242' };
		getCachedPreferredCard.mockReturnValue( cached );
		fetchPreferredCard.mockRejectedValue( new Error( 'timeout' ) );

		const { result } = renderHook( () => usePreferredCard() );

		await waitFor( () => {
			expect( fetchPreferredCard ).toHaveBeenCalledTimes( 1 );
		} );
		expect( result.current ).toEqual( cached );
		expect( setCachedPreferredCard ).not.toHaveBeenCalled();
	} );
} );
