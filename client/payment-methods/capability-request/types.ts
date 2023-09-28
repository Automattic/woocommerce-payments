/**
 * External dependencies
 */
import { Status } from '@wordpress/notices';
import { Notice } from '@wordpress/components';

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

interface CapabilityActionButton extends Notice.ButtonAction {
	isBusy?: boolean;
	disabled?: boolean;
}
interface URLAction extends Notice.BaseAction {
	url: string;
}

export type CapabilityAction = CapabilityActionButton | URLAction;
