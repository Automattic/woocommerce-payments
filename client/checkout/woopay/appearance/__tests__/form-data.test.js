/**
 * Internal dependencies
 */
import { appendAppearanceToFormData } from '../form-data';

describe( 'appendAppearanceToFormData', () => {
	test( 'appends flat object with default prefix', () => {
		const formData = new FormData();
		appendAppearanceToFormData( formData, { color: '#fff', size: '16px' } );

		expect( formData.get( 'appearance[color]' ) ).toBe( '#fff' );
		expect( formData.get( 'appearance[size]' ) ).toBe( '16px' );
	} );

	test( 'appends nested objects recursively', () => {
		const formData = new FormData();
		appendAppearanceToFormData( formData, {
			rules: { '.Input': { color: '#333' } },
		} );

		expect( formData.get( 'appearance[rules][.Input][color]' ) ).toBe(
			'#333'
		);
	} );

	test( 'uses custom prefix', () => {
		const formData = new FormData();
		appendAppearanceToFormData( formData, { theme: 'stripe' }, 'data' );

		expect( formData.get( 'data[theme]' ) ).toBe( 'stripe' );
	} );

	test( 'handles empty object without errors', () => {
		const formData = new FormData();
		appendAppearanceToFormData( formData, {} );

		expect( [ ...formData.entries() ] ).toHaveLength( 0 );
	} );
} );
