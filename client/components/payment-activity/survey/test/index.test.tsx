/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import Survey from '..';
import WcPayOverviewSurveyContext, {
	WcPayOverviewSurveyContextProvider,
} from '../context';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createErrorNotice: jest.fn() } ) ),
} ) );

describe( 'WcPayOverviewSurveyContextProvider', () => {
	afterEach( () => {
		jest.clearAllMocks();
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
			responseStatus: 'resolved',
		} );
	} );

	it( 'should render survey emoji buttons and be clickable', () => {
		render(
			<WcPayOverviewSurveyContextProvider>
				<Survey />
			</WcPayOverviewSurveyContextProvider>
		);

		const surveyText = screen.getByText( 'Are these metrics helpful?' );
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

	test( 'test survey initial display', () => {
		const { container } = render(
			<WcPayOverviewSurveyContextProvider>
				<Survey />
			</WcPayOverviewSurveyContextProvider>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'test survey with comments textbox', () => {
		const { container } = render(
			<WcPayOverviewSurveyContextProvider>
				<Survey />
			</WcPayOverviewSurveyContextProvider>
		);

		const buttons = screen.getAllByRole( 'button' );
		fireEvent.click( buttons[ 1 ] );

		expect( container ).toMatchSnapshot();
	} );
} );
