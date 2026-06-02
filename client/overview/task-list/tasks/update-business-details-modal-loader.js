/** @format **/

export const renderUpdateBusinessDetailsModal = async ( props ) => {
	const { renderUpdateBusinessDetailsModal: renderModal } = await import(
		'./update-business-details-modal-renderer'
	);

	renderModal( props );
};
