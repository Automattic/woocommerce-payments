/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProgressBar from '..';

describe( 'ProgressBar', () => {
	test( 'renders correctly if progress is low', () => {
		const { container } = render( <ProgressBar progressLabel="10% completed" totalLabel="$1000000" progress={ 0.1 } /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly if progress is near completion', () => {
		const { container } = render( <ProgressBar progressLabel="90% completed" totalLabel="$1000000" progress={ 0.9 } /> );
		expect( container ).toMatchSnapshot();
	} );
} );
