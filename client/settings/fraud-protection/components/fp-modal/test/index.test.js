/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import {
	HighFraudProtectionModal,
	StandardFraudProtectionModal,
} from '../index';

describe( 'HighFraudProtectionModal', () => {
	const setHighModalOpen = jest.fn();

	it( 'renders the high protection level content with USD store currency in the modal body', () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const highUSDContentModal = screen.getByRole( 'document' );

		expect( highUSDContentModal ).toMatchSnapshot();
	} );

	it( 'renders the high protection level content with non-USD store currency in the modal body', () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const highEURContentModal = screen.getByRole( 'document' );

		expect( highEURContentModal ).toMatchSnapshot();
	} );
} );

describe( 'StandardFraudProtectionModal', () => {
	const setStandardModalOpen = jest.fn();

	it( 'renders the standard protection level content with USD store currency in the modal body', () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardUSDContentModal = screen.getByRole( 'document' );

		expect( standardUSDContentModal ).toMatchSnapshot();
	} );

	it( 'renders the standard protection level content with non-USD store currency in the modal body', () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardEURContentModal = screen.getByRole( 'document' );

		expect( standardEURContentModal ).toMatchSnapshot();
	} );
} );
