/** @format **/

export const renderSetupLivePaymentsModal = async () => {
	const { renderSetupLivePaymentsModal: renderModal } = await import(
		'./go-live-modal-renderer'
	);

	renderModal();
};
