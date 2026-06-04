/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react-hooks';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useReportExport } from '../use-report-export';

// Mock external dependencies
jest.mock( '@wordpress/api-fetch' );
jest.mock( '@wordpress/data' );

const mockCreateNotice = jest.fn();
const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;
const mockUseDispatch = useDispatch as jest.MockedFunction< any >;

const maxRetries = 5; // Match the value in the hook

describe( 'useReportExport', () => {
	// Common test props
	const testProps = {
		exportRequestURL: '/wc/v3/payments/transactions/download',
		exportFileAvailabilityEndpoint: '/wc/v3/payments/transactions/download',
		userEmail: 'test@example.com',
	};

	beforeEach( () => {
		// Reset all mocks before each test
		jest.clearAllMocks();
		jest.useFakeTimers();

		// Mock useDispatch
		mockUseDispatch.mockReturnValue( {
			createNotice: mockCreateNotice,
		} );

		// Mock document.createElement
		const mockLink = {
			href: '',
			click: jest.fn(),
		};
		jest.spyOn( document, 'createElement' ).mockReturnValue(
			mockLink as any
		);
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'should handle successful export request and immediate download', async () => {
		// Mock successful export request
		mockApiFetch
			.mockResolvedValueOnce( { export_id: '123' } )
			.mockResolvedValueOnce( {
				status: 'success',
				download_url: 'https://exports.wordpress.com/file.csv',
			} );

		const { result } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( testProps );
			jest.runAllTimers();
		} );

		// Verify API calls
		expect( mockApiFetch ).toHaveBeenCalledTimes( 2 );
		expect( mockApiFetch ).toHaveBeenNthCalledWith( 1, {
			path: testProps.exportRequestURL,
			method: 'POST',
		} );
		expect( mockApiFetch ).toHaveBeenNthCalledWith( 2, {
			path: `${ testProps.exportFileAvailabilityEndpoint }/123`,
			method: 'GET',
		} );

		// Verify isExportInProgress state
		expect( result.current.isExportInProgress ).toBe( false );
	} );

	it( 'should handle failed export request', async () => {
		// Mock failed export request
		mockApiFetch.mockRejectedValueOnce( new Error( 'Failed to export' ) );

		const { result } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( testProps );
		} );

		// Verify error notice
		expect( mockCreateNotice ).toHaveBeenCalledWith(
			'error',
			expect.stringContaining( 'problem generating' )
		);

		// Verify isExportInProgress state
		expect( result.current.isExportInProgress ).toBe( false );
	} );

	it( 'should handle maximum retries scenario', async () => {
		// Mock successful export request but pending file status
		mockApiFetch
			.mockResolvedValueOnce( { export_id: '123' } )
			.mockResolvedValue( { status: 'pending' } ); // All subsequent calls return pending

		const { result } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( testProps );
		} );

		// Need to wait for all polling attempts
		await act( async () => {
			// Run each polling attempt
			for ( let i = 0; i < maxRetries; i++ ) {
				jest.advanceTimersByTime( 1000 ); // Advance by polling interval
				// Wait for any pending promises to resolve
				await Promise.resolve();
			}
		} );

		// Verify multiple polling attempts
		expect( mockApiFetch ).toHaveBeenCalledTimes( 6 ); // 1 initial + 5 retries

		// Verify the API calls were made correctly
		expect( mockApiFetch ).toHaveBeenNthCalledWith( 1, {
			path: testProps.exportRequestURL,
			method: 'POST',
		} );

		// Verify subsequent calls were for checking file status
		for ( let i = 2; i <= 6; i++ ) {
			expect( mockApiFetch ).toHaveBeenNthCalledWith( i, {
				path: `${ testProps.exportFileAvailabilityEndpoint }/123`,
				method: 'GET',
			} );
		}

		// Verify isExportInProgress state
		expect( result.current.isExportInProgress ).toBe( false );
	} );

	it( 'should handle polling errors gracefully', async () => {
		mockApiFetch
			.mockResolvedValueOnce( { export_id: '123' } )
			.mockRejectedValue( new Error( 'error' ) );

		const { result } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( testProps );
		} );

		await act( async () => {
			for ( let i = 0; i < maxRetries; i++ ) {
				jest.advanceTimersByTime( 1000 );
				await Promise.resolve();
			}
		} );

		expect( mockApiFetch ).toHaveBeenCalledTimes( 6 );
		expect( result.current.isExportInProgress ).toBe( false );
	} );

	it( 'should cleanup timeout on unmount', async () => {
		// Mock successful export request
		mockApiFetch
			.mockResolvedValueOnce( { export_id: '123' } )
			.mockResolvedValue( { status: 'pending' } );

		const { result, unmount } = renderHook( () => useReportExport() );

		// Start the polling process
		await act( async () => {
			await result.current.requestReportExport( testProps );
		} );

		// Spy on clearTimeout after polling has started
		const clearTimeoutSpy = jest.spyOn( global, 'clearTimeout' );

		// Unmount the component
		unmount();

		expect( clearTimeoutSpy ).toHaveBeenCalled();
	} );

	it( 'invokes onSuccess when polling returns a download URL', async () => {
		mockApiFetch
			.mockResolvedValueOnce( { export_id: 'abc' } )
			.mockResolvedValueOnce( {
				status: 'success',
				download_url: 'https://example.test/file.csv',
			} );
		const onSuccess = jest.fn();

		const { result, unmount } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( {
				...testProps,
				onSuccess,
			} );
			jest.advanceTimersByTime( 1000 );
			await Promise.resolve();
		} );

		unmount();

		expect( onSuccess ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'invokes onError with reason "request" when the POST throws', async () => {
		mockApiFetch.mockRejectedValueOnce( new Error( 'Failed to export' ) );
		const onError = jest.fn();

		const { result } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( {
				...testProps,
				onError,
			} );
		} );

		expect( onError ).toHaveBeenCalledWith( { reason: 'request' } );
	} );

	it( 'recovers and invokes onError when the POST resolves without an export_id', async () => {
		// A 2xx response that omits export_id must not leave the request
		// hanging — the UI should recover and the failure should be tracked.
		mockApiFetch.mockResolvedValueOnce( {} );
		const onError = jest.fn();
		const onSuccess = jest.fn();

		const { result } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( {
				...testProps,
				onError,
				onSuccess,
			} );
		} );

		// Only the POST is attempted; polling never starts.
		expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
		expect( onError ).toHaveBeenCalledWith( { reason: 'request' } );
		expect( onSuccess ).not.toHaveBeenCalled();
		expect( result.current.isExportInProgress ).toBe( false );
	} );

	it( 'invokes onError with reason "timeout" when polling exhausts retries', async () => {
		mockApiFetch
			.mockResolvedValueOnce( { export_id: 'abc' } )
			.mockResolvedValue( { status: 'pending' } );
		const onError = jest.fn();

		const { result, unmount } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( {
				...testProps,
				onError,
			} );
		} );

		await act( async () => {
			for ( let i = 0; i < maxRetries; i++ ) {
				jest.advanceTimersByTime( 1000 );
				await Promise.resolve();
			}
		} );

		unmount();

		expect( onError ).toHaveBeenCalledWith( { reason: 'timeout' } );
	} );

	it( 'does not reuse retry count across export requests from the same hook instance', async () => {
		mockApiFetch
			.mockResolvedValueOnce( { export_id: 'first-export' } )
			.mockResolvedValue( { status: 'pending' } );
		const firstOnError = jest.fn();
		const secondOnError = jest.fn();

		const { result, unmount } = renderHook( () => useReportExport() );

		try {
			await act( async () => {
				await result.current.requestReportExport( {
					...testProps,
					onError: firstOnError,
				} );
			} );

			await act( async () => {
				for ( let i = 0; i < maxRetries; i++ ) {
					jest.advanceTimersByTime( 1000 );
					await Promise.resolve();
				}
			} );

			expect( firstOnError ).toHaveBeenCalledWith( {
				reason: 'timeout',
			} );

			mockApiFetch.mockReset();
			mockApiFetch
				.mockResolvedValueOnce( { export_id: 'second-export' } )
				.mockResolvedValue( { status: 'pending' } );

			await act( async () => {
				await result.current.requestReportExport( {
					...testProps,
					onError: secondOnError,
				} );
			} );

			await act( async () => {
				jest.advanceTimersByTime( 1000 );
				await Promise.resolve();
			} );

			expect( secondOnError ).not.toHaveBeenCalled();

			await act( async () => {
				for ( let i = 0; i < maxRetries - 1; i++ ) {
					jest.advanceTimersByTime( 1000 );
					await Promise.resolve();
				}
			} );

			expect( secondOnError ).toHaveBeenCalledTimes( 1 );
			expect( secondOnError ).toHaveBeenCalledWith( {
				reason: 'timeout',
			} );
		} finally {
			unmount();
		}
	} );

	it( 'preserves existing behavior when no callbacks are passed', async () => {
		mockApiFetch
			.mockResolvedValueOnce( { export_id: 'abc' } )
			.mockResolvedValueOnce( {
				status: 'success',
				download_url: 'https://example.test/file.csv',
			} );

		const { result, unmount } = renderHook( () => useReportExport() );

		await act( async () => {
			await result.current.requestReportExport( testProps );
			jest.advanceTimersByTime( 1000 );
			await Promise.resolve();
		} );

		unmount();

		expect( result.current.isExportInProgress ).toBe( false );
	} );
} );
