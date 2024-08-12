// This type was imported from @wordpress/notices but it's not longer exported by the module.
type Status = 'success' | 'info' | 'error' | 'warning';

export interface CapabilityStatus {
	status: Status;
	content: string;
	actions?: string;
	actionsLabel?: string;
	actionUrl?: string;
}

export interface CapabilityRequestMap {
	id: string;
	label: string;
	country?: string;
	states: Record< string, CapabilityStatus >;
}

export interface CapabilityNoticeProps {
	id: string;
	label: string;
	country?: string;
	states: Record< string, CapabilityStatus >;
}

export interface DismissConfirmationModalProps {
	onClose: () => void;
	onSubmit: () => void;
	label: string;
}
