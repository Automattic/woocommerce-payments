/* eslint-disable camelcase */
/**
 * External dependencies
 */
import { __, _n } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { EmptyContent, Section } from '@woocommerce/components';
import {
	NOTES_STORE_NAME,
	useUserPreferences,
	QUERY_DEFAULTS,
} from '@woocommerce/data';
import { useSelect, useDispatch } from '@wordpress/data';
import { CSSTransition, TransitionGroup } from 'react-transition-group';
import {
	InboxNoteCard,
	InboxDismissConfirmationModal,
	InboxNotePlaceholder,
} from '@woocommerce/experimental';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';
import './index.scss';

const INBOX_QUERY = {
	page: 1,
	per_page: QUERY_DEFAULTS.pageSize,
	status: 'unactioned',
	type: QUERY_DEFAULTS.noteTypes,
	orderby: 'date',
	order: 'desc',
	source: 'woocommerce-payments',
	_fields: [
		'id',
		'name',
		'title',
		'content',
		'type',
		'status',
		'actions',
		'date_created',
		'date_created_gmt',
		'layout',
		'image',
		'is_deleted',
		'source',
	],
};

const renderEmptyCard = () => (
	<section className="woocommerce-empty-activity-card">
		{ __(
			'As things begin to happen in your store your inbox will start to fill up. ' +
				"You'll see things like achievements, new feature announcements, extension recommendations and more!",
			'woocommerce-admin'
		) }
	</section>
);

function hasValidNotes( notes ) {
	const validNotes = notes.filter( ( { is_deleted } ) => {
		return ! is_deleted;
	} );
	return 0 <= validNotes.length;
}

const onBodyLinkClick = ( note, innerLink ) => {
	wcpayTracks.recordEvent( 'wcpay_inbox_action_click', {
		note_name: note.name,
		note_title: note.title,
		note_content_inner_link: innerLink,
	} );
};

const renderNotes = ( {
	hasNotes,
	batchUpdating,
	lastRead,
	notes,
	onDismiss,
	onNoteActionClick,
} ) => {
	if ( batchUpdating ) {
		return;
	}

	if ( ! hasNotes ) {
		return renderEmptyCard();
	}

	const onNoteVisible = ( note ) => {
		wcpayTracks.recordEvent( 'wcpay_inbox_note_view', {
			note_content: note.content,
			note_name: note.name,
			note_title: note.title,
			note_type: note.type,
		} );
	};

	const notesArray = Object.keys( notes ).map( ( key ) => notes[ key ] );

	return (
		<TransitionGroup role="menu">
			{ notesArray.map( ( note ) => {
				const { id: noteId, is_deleted: isDeleted } = note;
				if ( isDeleted ) {
					return null;
				}
				return (
					<CSSTransition
						key={ noteId }
						timeout={ 500 }
						classNames="wcpay-inbox-message"
					>
						<InboxNoteCard
							key={ noteId }
							note={ note }
							lastRead={ lastRead }
							onDismiss={ onDismiss }
							onNoteActionClick={ onNoteActionClick }
							onBodyLinkClick={ onBodyLinkClick }
							onNoteVisible={ onNoteVisible }
						/>
					</CSSTransition>
				);
			} ) }
		</TransitionGroup>
	);
};

const InboxPanel = () => {
	const { updateUserPreferences, ...userPrefs } = useUserPreferences();
	const { createNotice } = useDispatch( 'core/notices' );
	const {
		batchUpdateNotes,
		removeAllNotes,
		removeNote,
		updateNote,
		triggerNoteAction,
	} = useDispatch( NOTES_STORE_NAME );
	const { isError, resolving, batchUpdating, notes } = useSelect(
		( select ) => {
			const {
				getNotes,
				getNotesError,
				isResolving,
				isNotesRequesting,
			} = select( NOTES_STORE_NAME );

			return {
				notes: getNotes( INBOX_QUERY ),
				isError: Boolean(
					getNotesError( 'getNotes', [ INBOX_QUERY ] )
				),
				resolving: isResolving( 'getNotes', [ INBOX_QUERY ] ),
				batchUpdating: isNotesRequesting( 'batchUpdateNotes' ),
			};
		}
	);
	const [ dismiss, setDismiss ] = useState();
	const [ lastRead ] = useState(
		userPrefs.wc_payments_overview_inbox_last_read
	);

	useEffect( () => {
		const mountTime = Date.now();

		const userDataFields = {
			wc_payments_overview_inbox_last_read: mountTime,
		};
		updateUserPreferences( userDataFields );
	} );

	if ( isError ) {
		const title = __(
			'There was an error getting your inbox. Please try again.',
			'woocommerce-admin'
		);
		const actionLabel = __( 'Reload', 'woocommerce-admin' );
		const actionCallback = () => {
			// @todo Add tracking for how often an error is displayed, and the reload action is clicked.
			window.location.reload();
		};

		return (
			<EmptyContent
				title={ title }
				actionLabel={ actionLabel }
				actionURL={ null }
				actionCallback={ actionCallback }
			/>
		);
	}

	const onDismiss = ( note, type ) => {
		setDismiss( { note, type } );
	};

	const closeDismissModal = async ( confirmed = false ) => {
		const noteNameDismissAll = 'all' === dismiss.type ? true : false;

		wcpayTracks.recordEvent( 'wcpay_inbox_action_dismiss', {
			note_name: dismiss.note.name,
			note_title: dismiss.note.title,
			note_name_dismiss_all: noteNameDismissAll,
			note_name_dismiss_confirmation: confirmed,
		} );

		if ( confirmed ) {
			const noteId = dismiss.note.id;
			const removeAll = ! noteId || noteNameDismissAll;
			try {
				let notesRemoved = [];
				if ( removeAll ) {
					notesRemoved = await removeAllNotes();
				} else {
					const noteRemoved = await removeNote( noteId );
					notesRemoved = [ noteRemoved ];
				}
				setDismiss( undefined );
				createNotice(
					'success',
					1 < notesRemoved.length
						? __( 'All messages dismissed', 'woocommerce-admin' )
						: __( 'Message dismissed', 'woocommerce-admin' ),
					{
						actions: [
							{
								label: __( 'Undo', 'woocommerce-admin' ),
								onClick: () => {
									if ( 1 < notesRemoved.length ) {
										batchUpdateNotes(
											notesRemoved.map(
												( note ) => note.id
											),
											{
												is_deleted: 0,
											}
										);
									} else {
										updateNote( noteId, {
											is_deleted: 0,
										} );
									}
								},
							},
						],
					}
				);
			} catch ( e ) {
				const numberOfNotes = removeAll ? notes.length : 1;
				createNotice(
					'error',
					_n(
						'Message could not be dismissed',
						'Messages could not be dismissed',
						numberOfNotes,
						'woocommerce-admin'
					)
				);
				setDismiss( undefined );
			}
		} else {
			setDismiss( undefined );
		}
	};

	const onNoteActionClick = ( note, action ) => {
		triggerNoteAction( note.id, action.id );
	};

	const hasNotes = hasValidNotes( notes );

	return (
		<>
			<div className="wcpay-overview-screen-notes-wrapper">
				{ ( resolving || batchUpdating ) && (
					<Section>
						<InboxNotePlaceholder className="banner message-is-unread" />
					</Section>
				) }
				<Section>
					{ ! resolving &&
						! batchUpdating &&
						renderNotes( {
							hasNotes,
							batchUpdating,
							lastRead,
							notes,
							onDismiss,
							onNoteActionClick,
						} ) }
				</Section>
				{ dismiss && (
					<InboxDismissConfirmationModal
						onClose={ closeDismissModal }
						onDismiss={ () => closeDismissModal( true ) }
					/>
				) }
			</div>
		</>
	);
};

export default InboxPanel;
