/**
 * External dependencies
 */
import 'whatwg-fetch';

/**
 * Internal dependencies
 */
import { server } from './utilities/msw-server';

let handleUnhandledRequest;
beforeAll( () => {
	handleUnhandledRequest = jest.fn().mockImplementation( ( req ) => {
		console.error( `
[jest-msw-setup.js]
Error: intercepted a request without a matching request handler:

• ${ req.method } ${ req.url }

Please create a request handler for it.` );
	} );

	// ensures that all requests made in the tests are handled.
	// if a request is made without its handler, this will throw an error.
	server.listen( { onUnhandledRequest: handleUnhandledRequest } );
	server.events.on( 'request:unhandled', handleUnhandledRequest );
} );

afterEach( () => {
	// ensures the test fails if the handler has been called.
	expect( handleUnhandledRequest ).not.toHaveBeenCalled();

	// ensures the request handlers between each test do not leak to other, irrelevant tests.
	server.resetHandlers();
} );

afterAll( () => {
	server.close();
} );
