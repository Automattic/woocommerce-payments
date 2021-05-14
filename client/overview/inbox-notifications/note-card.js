/**
 * External dependencies
 */
import { useRef } from 'react';
import classnames from 'classnames';
import { H, Section } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import './note-card.scss';

function InboxNoteCard( { note, lastRead } ) {
	const bodyNotificationRef = useRef();

	const {
		content,
		date_created: dateCreated,
		date_created_gmt: dateCreatedGmt,
		image,
		is_deleted: isDeleted,
		layout,
		status,
		title,
	} = note;
	const unread =
		! lastRead ||
		! dateCreatedGmt ||
		new Date( dateCreatedGmt + 'Z' ).getTime() > lastRead;
	const date = dateCreated;
	const hasImage = layout !== 'plain' && layout !== '';
	const cardClassName = classnames( 'woocommerce-inbox-message', layout, {
		'message-is-unread': unread && status === 'unactioned',
	} );

	return (
		<section className={ cardClassName }>
			{ hasImage && (
				<div className="woocommerce-inbox-message__image">
					<img src={ image } alt="" />
				</div>
			) }
			<div className="woocommerce-inbox-message__wrapper">
				<div className="woocommerce-inbox-message__content">
					{ unread && (
						<div className="woocommerce-inbox-message__unread-indicator" />
					) }
					{ date && (
						<span className="woocommerce-inbox-message__date">
							{ /* { moment.utc( date ).fromNow() } */ }
						</span>
					) }
					<H className="woocommerce-inbox-message__title">
						{ title }
					</H>
					<Section className="woocommerce-inbox-message__text">
						<span
							dangerouslySetInnerHTML={
								{
									__html: content,
								} /* sanitizeHTML(
										content
                                    )*/
							}
							ref={ bodyNotificationRef }
						/>
					</Section>
				</div>
				<div className="woocommerce-inbox-message__actions">
					{ /* { this.renderActions( note ) }
							{ this.renderDismissButton() } */ }
				</div>
			</div>
			{ /* { isDismissModalOpen &&
						this.renderDismissConfirmationModal() } */ }
		</section>
	);
}

export default InboxNoteCard;
