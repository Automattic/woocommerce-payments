/**
 * External dependencies
 */
import React from 'react';
import { dispatch } from '@wordpress/data';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */

import WcPaySurveyContextProvider from '../provider';
import WcPaySurveyContext from '../context';
import SurveyModal from '..';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/data' );

describe( 'WcPaySurveyContextProvider', () => {
	beforeEach( () => {
		const noticesDispatch = {
			createSuccessNotice: jest.fn(),
			createErrorNotice: jest.fn(),
		};

		dispatch.mockImplementation( ( storeName ) => {
			if ( 'core/notices' === storeName ) {
				return noticesDispatch;
			}

			return {};
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();

		apiFetch.mockResolvedValue( true );
	} );

	afterAll( () => {
		jest.restoreAllMocks();
	} );

	it( 'should render the initial state', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<WcPaySurveyContextProvider>
				<WcPaySurveyContext.Consumer>
					{ childrenMock }
				</WcPaySurveyContext.Consumer>
			</WcPaySurveyContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isSurveySubmitted: false,
			submitSurvey: expect.any( Function ),
			status: 'resolved',
			surveyAnswers: { 'why-disable': 'slow-buggy' },
			setSurveyAnswers: expect.any( Function ),
		} );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'should render survey questions radio buttons and be clickable', () => {
		// @todo: mock the setSurveySubmitted hook from useSurveySubmit to actually click send feedback.
		const setOpenModalIdentifier = jest.fn().mockReturnValue( true );
		render(
			<WcPaySurveyContextProvider>
				<SurveyModal
					setOpenModal={ setOpenModalIdentifier }
					surveyKey="wcpay-upe-disable-early-access"
					surveyQuestion="why-disable"
				/>
			</WcPaySurveyContextProvider>
		);
		const radioButtons = screen.getAllByRole( 'radio', {} );
		userEvent.click( radioButtons[ 0 ] );
		expect( radioButtons[ 0 ] ).toBeChecked();
	} );
} );
