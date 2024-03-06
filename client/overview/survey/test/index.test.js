/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import Survey from 'wcpay/overview/survey';
import WcPayOverviewSurveyContext, {
	WcPayOverviewSurveyContextProvider,
} from 'wcpay/overview/survey/context';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/data' );

describe( 'WcPayOverviewSurveyContextProvider', () => {
	afterEach( () => {
		jest.clearAllMocks();

		apiFetch.mockResolvedValue( true );
	} );

	afterAll( () => {
		jest.restoreAllMocks();
	} );

	it( 'render the initial state', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<WcPayOverviewSurveyContextProvider>
				<WcPayOverviewSurveyContext.Consumer>
					{ childrenMock }
				</WcPayOverviewSurveyContext.Consumer>
			</WcPayOverviewSurveyContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			setSurveyAnswers: expect.any( Function ),
			setSurveySubmitted: expect.any( Function ),
			surveyAnswers: {},
			surveySubmitted: false,
			status: 'resolved',
		} );
	} );

	it( 'should render survey questions radio buttons and be clickable', () => {
		render(
			<WcPayOverviewSurveyContextProvider>
				<Survey />
			</WcPayOverviewSurveyContextProvider>
		);

		const surveyText = screen.getByText(
			'How do you like your new finance overview?'
		);
		expect( surveyText ).toBeInTheDocument();

		const buttons = screen.getAllByRole( 'button' );

		//show comments field
		fireEvent.click( buttons[ 0 ] );
		const commentLabel = screen.getByText(
			'Why do you feel that way? (optional)'
		);
		expect( commentLabel ).toBeInTheDocument();

		//show comments field
		fireEvent.click( buttons[ 1 ] );
		expect( commentLabel ).toBeInTheDocument();

		//show comments field
		fireEvent.click( buttons[ 2 ] );
		expect( commentLabel ).toBeInTheDocument();

		//hide comments field and submit survey
		fireEvent.click( buttons[ 3 ] );
		expect( commentLabel ).not.toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );
} );
