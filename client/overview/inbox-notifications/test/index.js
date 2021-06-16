/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { updateWoocommerceUserMeta } from 'utils/update-woocommerce-user-meta';
import InboxPanel from '..';

jest.mock( '@wordpress/data' );
jest.mock( '@woocommerce/components', () => {
	return {
		EmptyContent: () => <div>empty-content</div>,
		Section: ( { children } ) => <>{ children }</>,
	};
} );
jest.mock( '@woocommerce/experimental', () => {
	return {
		__esModule: true,
		InboxNoteCard: ( { note } ) => <div>{ note.title }</div>,
		InboxDismissConfirmationModal: () => <div>confirmation_model</div>,
		InboxNotePlaceholder: () => <div>placeholder</div>,
	};
} );

jest.mock( 'utils/update-woocommerce-user-meta', () => ( {
	__esModule: true,
	updateWoocommerceUserMeta: jest.fn(),
} ) );

const defaultNotes = [
	{
		id: 1,
		title: 'test 1',
		date_created_gmt: '2019-05-10T16:57:31',
		is_deleted: false,
		status: 'unactioned',
	},
	{
		id: 2,
		title: 'test 2',
		date_created_gmt: '2020-05-12T16:57:31',
		is_deleted: false,
		status: 'unactioned',
	},
	{
		id: 3,
		title: 'test 3',
		date_created_gmt: '2020-05-14T16:57:31',
		is_deleted: false,
		status: 'unactioned',
	},
];

describe( 'InboxPanel', () => {
	let batchUpdateNotes,
		removeAllNotes,
		removeNote,
		updateNote,
		triggerNoteAction,
		createNotice;
	const defaultUseSelectData = {
		isError: false,
		resolving: false,
		batchUpdating: false,
		notes: defaultNotes,
		overviewInboxLastRead: 123123123,
	};
	beforeEach( () => {
		batchUpdateNotes = jest.fn();
		removeAllNotes = jest.fn();
		removeNote = jest.fn();
		updateNote = jest.fn();
		triggerNoteAction = jest.fn();
		createNotice = jest.fn();
		useDispatch.mockImplementation( () => ( {
			batchUpdateNotes,
			removeAllNotes,
			removeNote,
			updateNote,
			triggerNoteAction,
			createNotice,
		} ) );
		useSelect.mockImplementation( () => ( { ...defaultUseSelectData } ) );
	} );

	test( 'it should call updateWoocommerceUserMeta with new last_read on first render', () => {
		render( <InboxPanel /> );
		expect( updateWoocommerceUserMeta ).toHaveBeenCalled();
		expect(
			updateWoocommerceUserMeta.mock.calls[ 0 ][ 0 ]
				.wc_payments_overview_inbox_last_read
		).toBeDefined();
	} );

	test( 'it should only render the inbox note place holder when resolving is true', () => {
		useSelect.mockImplementation( () => ( {
			...defaultUseSelectData,
			resolving: true,
		} ) );
		const { queryByText } = render( <InboxPanel /> );
		expect( queryByText( 'placeholder' ) ).toBeInTheDocument();
	} );

	test( 'it should only render the inbox note place holder when batchUpdating is true', () => {
		useSelect.mockImplementation( () => ( {
			...defaultUseSelectData,
			batchUpdating: true,
		} ) );
		const { queryByText } = render( <InboxPanel /> );
		expect( queryByText( 'placeholder' ) ).toBeInTheDocument();
	} );

	test( 'it should render notes when neither resolving or batchUpdating is true', () => {
		useSelect.mockImplementation( () => ( {
			...defaultUseSelectData,
		} ) );
		const { queryByText } = render( <InboxPanel /> );
		for ( const note of defaultUseSelectData.notes ) {
			expect( queryByText( note.title ) ).toBeInTheDocument();
		}
	} );

	test( 'it should render empty card if not notes available', () => {
		useSelect.mockImplementation( () => ( {
			...defaultUseSelectData,
			notes: [],
		} ) );
		const { container } = render( <InboxPanel /> );

		expect(
			container.querySelector( '.woocommerce-empty-activity-card' )
		).toBeInTheDocument();
	} );
} );
