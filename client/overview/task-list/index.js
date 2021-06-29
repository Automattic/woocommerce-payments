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

const DAY_IN_MS = 24 * 60 * 60 * 1000;

const TaskList = ( {
	collapsible = false,
	dismissedTasks,
	remindMeLaterTasks,
	tasks,
} ) => {
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

	const undoRemindTaskLater = ( key ) => {
		const {
			// eslint-disable-next-line no-unused-vars
			[ key ]: oldValue,
			...updatedRemindMeLaterTasks
		} = remindMeLaterTasks;

		/*eslint-disable camelcase*/
		updateOptions( {
			woocommerce_remind_me_later_tasks_todo: updatedRemindMeLaterTasks,
		} );
		/*eslint-enable camelcase*/
	};

	const remindTaskLater = ( { key, onDismiss } ) => {
		createNotice(
			'success',
			__( 'Task postponed until tomorrow', 'woocommerce-admin' ),
			{
				actions: [
					{
						label: __( 'Undo', 'woocommerce-admin' ),
						onClick: () => undoRemindTaskLater( key ),
					},
				],
			}
		);

		const dismissTime = Date.now() + DAY_IN_MS;
		/*eslint-disable camelcase*/
		updateOptions( {
			woocommerce_remind_me_later_tasks_todo: {
				...remindMeLaterTasks,
				[ key ]: dismissTime,
			},
		} );
		/*eslint-enable camelcase*/
		if ( onDismiss ) {
			onDismiss();
		}
	};

	const nowTimestamp = Date.now();
	const visibleTasks = tasks.filter(
		( task ) =>
			! dismissedTasks.includes( task.key ) &&
			( ! remindMeLaterTasks[ task.key ] ||
				remindMeLaterTasks[ task.key ] < nowTimestamp )
	);

	const ListComp = collapsible ? CollapsibleList : List;

	const listProps = collapsible
		? {
				collapseLabel: __( 'Hide tasks', 'woocommerce-payments' ),
				expandLabel: __( 'Show tasks', 'woocommerce-payments' ),
				collapsed: false,
		  }
		: {};

	const pendingTaskCount = visibleTasks.filter( ( task ) => ! task.completed )
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
					{ visibleTasks.map( ( task ) => (
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
							remindMeLater={
								task.allowRemindMeLater
									? () => remindTaskLater( task )
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
