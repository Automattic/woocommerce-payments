/**
 * External dependencies
 */
import { TaskItem } from '@woocommerce/experimental';

export interface TaskItemProps extends React.ComponentProps< typeof TaskItem > {
	/**
	 * Unique key for the task.
	 */
	key: string;
	/**
	 * Used to pass data attributes be rendered with a task, e.g. `data-urgent="true"`.
	 */
	dataAttrs?: Record< string, string | boolean >;

	/**
	 * Whether the task is dismissable.
	 */
	isDismissable?: boolean;
}
