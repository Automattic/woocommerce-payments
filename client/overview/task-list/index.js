/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { Badge } from '@woocommerce/components';
import {
	CollapsibleList,
	List,
	TaskItem,
	Text,
} from '@woocommerce/experimental';
import { useDispatch } from '@wordpress/data';

const TaskList = ( { collapsible = false, dismissedTasks, tasks } ) => {
	const { createNotice } = useDispatch( 'core/notices' );
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const undoDismissTask = ( key ) => {
		const updatedDismissedTasks = dismissedTasks.filter(
			( task ) => task !== key
		);

		/*eslint-disable camelcase*/
		updateOptions( {
			woocommerce_dismissed_tasks_todo: updatedDismissedTasks,
		} );
		/*eslint-enable camelcase*/
	};
	const dismissTask = ( { key, onDismiss } ) => {
		createNotice( 'success', __( 'Task dismissed' ), {
			actions: [
				{
					label: __( 'Undo', 'woocommerce-admin' ),
					onClick: () => undoDismissTask( key ),
				},
			],
		} );

		/*eslint-disable camelcase*/
		updateOptions( {
			woocommerce_dismissed_tasks_todo: [ ...dismissedTasks, key ],
		} );
		/*eslint-enable camelcase*/
		if ( onDismiss ) {
			onDismiss();
		}
	};

	const listTasks = tasks.filter(
		( task ) => ! dismissedTasks.includes( task.key )
	);

	const ListComp = collapsible ? CollapsibleList : List;

	const listProps = collapsible
		? {
				collapseLabel: __( 'Hide tasks', 'woocommerce-payments' ),
				expandLabel: __( 'Show tasks', 'woocommerce-payments' ),
				collapsed: false,
		  }
		: {};

	const pendingTaskCount = listTasks.filter( ( task ) => ! task.completed )
		.length;
	return (
		<Card
			size="large"
			className="woocommerce-task-card woocommerce-homescreen-card"
		>
			<CardHeader size="medium">
				<div className="wooocommerce-task-card__header">
					<Text variant="title.small">
						{ __( 'Things to do', 'woocommerce-payments' ) }
					</Text>
					{ 0 < pendingTaskCount && (
						<Badge count={ pendingTaskCount } />
					) }
				</div>
			</CardHeader>
			<CardBody>
				<ListComp animation="custom" { ...listProps }>
					{ listTasks.map( ( task ) => (
						<TaskItem
							key={ task.key }
							title={ task.title }
							completed={ task.completed }
							content={ task.content || task.additionalInfo }
							expanded
							action={ task.onClick }
							time={ task.time }
							level={ task.level }
							onDismiss={
								task.isDismissable
									? () => dismissTask( task )
									: undefined
							}
						/>
					) ) }
				</ListComp>
			</CardBody>
		</Card>
	);
};

export default TaskList;
