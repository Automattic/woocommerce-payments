/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import Placeholder from '../';

describe( 'Placeholder', () => {
	const ChildComponent = () => ( <div>ChildComponent</div> );
	let placeholderProps;

	describe( 'when active', () => {
		beforeEach( () => {
			placeholderProps = {
				isActive: true,
			};
		} );

		test( 'renders default placeholder value', () => {
			const placeholder = renderPlaceholder( placeholderProps );
			expect( placeholder.text() ).toBe( 'placeholder' );
		} );

		test( 'renders custom placeholder value', () => {
			const customContent = 'Custom text';
			placeholderProps.content = customContent;
			const placeholder = renderPlaceholder( placeholderProps );
			expect( placeholder.text() ).toBe( customContent );
		} );
	} );

	describe( 'when inactive', () => {
		test( 'render children', () => {
			placeholderProps = {
				isActive: false,
			};

			const placeholder = renderPlaceholder( placeholderProps );
			expect( placeholder.find( ChildComponent ).length ).toBe( 1 );
			expect( placeholder.text() ).not.toBe( 'placeholder' );
		} );
	} );

	function renderPlaceholder( props = {}, content = <ChildComponent /> ) {
	return shallow( <Placeholder { ...props }>{ content }</Placeholder> );
	}
} );
