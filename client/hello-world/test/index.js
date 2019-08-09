/** @format */
/**
 * External dependencies
 */
import renderer from 'react-test-renderer';

/**
 * Internal dependencies
 */
import { HelloWorld } from '../';

describe( 'HelloWorld', () => {
	test( 'it renders correctly', () => {
		const tree = renderer.create( <HelloWorld /> ).toJSON();
		expect( tree ).toMatchSnapshot();
	} );
} );
