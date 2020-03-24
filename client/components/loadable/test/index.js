/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import Loadable from '..';

describe( 'Loadable', () => {
	const ChildComponent = () => ( <div>Loaded content</div> );
	let loadableProps;

	describe( 'when active', () => {
		beforeEach( () => {
			loadableProps = {
				isLoading: true,
			};
		} );

		test( 'renders default loadable value', () => {
			const loadable = renderLoadable( loadableProps );
			expect( loadable.text() ).toBe( 'placeholder' );
		} );

		test( 'renders custom placeholder value', () => {
			const customPlaceholder = 'Custom text';
			loadableProps.placeholder = customPlaceholder;
			const loadable = renderLoadable( loadableProps );
			expect( loadable.text() ).toBe( customPlaceholder );
		} );
	} );

	describe( 'when inactive', () => {
		test( 'render children', () => {
			loadableProps = {
				isLoading: false,
			};

			const loadable = renderLoadable( loadableProps );
			expect( loadable.find( ChildComponent ).length ).toBe( 1 );
			expect( loadable.text() ).not.toBe( 'placeholder' );
		} );
	} );

	function renderLoadable( props = {}, content = <ChildComponent /> ) {
	return shallow( <Loadable { ...props }>{ content }</Loadable> );
	}
} );
