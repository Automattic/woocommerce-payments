/**
 * External dependencies
 */
import type { ComponentType, ReactNode } from 'react';

export interface PaymentMethodItemProps {
	id?: string;
	className?: string;
	children: ReactNode;
}

export interface PaymentMethodItemCheckboxProps {
	label: string;
	checked: boolean;
	disabled?: boolean;
	onChange: ( checked: boolean ) => void;
	// eslint-disable-next-line @typescript-eslint/naming-convention
	'data-testid'?: string;
}

export interface PaymentMethodItemBodyProps {
	children: ReactNode;
}

export interface PaymentMethodItemSubgroupProps {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	Icon?: ComponentType< Record< string, unknown > >;
	label: ReactNode;
	children?: ReactNode;
	className?: string;
}

export interface PaymentMethodItemActionProps {
	children: ReactNode;
	className?: string;
}
