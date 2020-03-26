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

		test( 'renders custom placeholder', () => {
			const customPlaceholder = 'Custom text';
			loadableProps.placeholder = customPlaceholder;
			const loadable = renderLoadable( loadableProps );
			expect( loadable.text() ).toBe( customPlaceholder );
		} );

		test( 'uses children as placeholder if not passed', () => {
			const loadable = renderLoadable( loadableProps );
			expect( loadable.find( ChildComponent ).length ).toBe( 1 );
			expect( loadable ).toMatchSnapshot();
		} );
	} );

	describe( 'when inactive', () => {
		beforeEach( () => {
			loadableProps = {
				isLoading: false,
			};
		} );

		test( 'render children', () => {
			const loadable = renderLoadable( loadableProps );
			expect( loadable.find( ChildComponent ).length ).toBe( 1 );
			expect( loadable.text() ).not.toBe( 'placeholder' );
		} );

		test( 'renders simple value', () => {
			const simpleValue = 'Simple loadable value';
			loadableProps.value = simpleValue;
			const loadable = renderLoadable( loadableProps, null );
			expect( loadable.find( ChildComponent ).length ).toBe( 0 );
			expect( loadable.text() ).toBe( simpleValue );
		} );

		test( 'prioritizes rendering children over simple value', () => {
			const simpleValue = 'Simple loadable value';
			loadableProps.value = simpleValue;
			const loadable = renderLoadable( loadableProps );
			expect( loadable.find( ChildComponent ).length ).toBe( 1 );
			expect( loadable.text() ).not.toBe( simpleValue );
		} );

		test( 'renders nothing when neither children nor value passed', () => {
			const loadable = renderLoadable( loadableProps, null );
			expect( loadable.find( ChildComponent ).length ).toBe( 0 );
			expect( loadable.text().length ).toBe( 0 );
		} );
	} );

	function renderLoadable( props = {}, content = <ChildComponent /> ) {
	return shallow( <Loadable { ...props }>{ content }</Loadable> );
	}
} );
