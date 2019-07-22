/**
 * NOTE: This is temporary code. It exists only until a version of `@wordpress/data`
 * is released which supports this functionality.
 *
 * @todo Remove this and use `@wordpress/data` `withSelect` instead after
 * this PR is merged: https://github.com/WordPress/gutenberg/pull/11460
 *
 * @format
 */

/**
 * External dependencies
 */
import { isFunction } from 'lodash';
import { Component } from '@wordpress/element';
import isShallowEqual from '@wordpress/is-shallow-equal';
import { createHigherOrderComponent } from '@wordpress/compose';
import { RegistryConsumer } from '@wordpress/data';

/**
 * Higher-order component used to inject state-derived props using registered
 * selectors.
 *
 * @param {Function} mapSelectToProps Function called on every state change,
 *                                   expected to return object of props to
 *                                   merge with the component's own props.
 *
 * @return {Component} Enhanced component with merged state data props.
 */
const withSelect = mapSelectToProps =>
	createHigherOrderComponent( WrappedComponent => {
		/**
		 * Default merge props. A constant value is used as the fallback since it
		 * can be more efficiently shallow compared in case component is repeatedly
		 * rendered without its own merge props.
		 *
		 * @type {Object}
		 */
		const DEFAULT_MERGE_PROPS = {};

		class ComponentWithSelect extends Component {
			constructor( props ) {
				super( props );

				this.onStoreChange = this.onStoreChange.bind( this );
				this.subscribe( props.registry );

				this.onUnmounts = {};
				this.mergeProps = this.getNextMergeProps( props );
			}

			/**
			 * Given a props object, returns the next merge props by mapSelectToProps.
			 *
			 * @param {Object} props Props to pass as argument to mapSelectToProps.
			 *
			 * @return {Object} Props to merge into rendered wrapped element.
			 */
			getNextMergeProps( props ) {
				const storeSelectors = {};
				const onCompletes = [];
				const componentContext = { component: this };

				const getStoreFromRegistry = ( key, registry, context ) => {
					// This is our first time selecting from this store.
					// Do some lazy-loading of handling at this time.
					const selectorsForKey = registry.select( key );

					if ( isFunction( selectorsForKey ) ) {
						// This store has special handling for its selectors.
						// We give it a context, and we check for a "resolve"
						const { selectors, onComplete, onUnmount } = selectorsForKey( context );
						onComplete && onCompletes.push( onComplete );
						onUnmount && ( this.onUnmounts[ key ] = onUnmount );
						storeSelectors[ key ] = selectors;
					} else {
						storeSelectors[ key ] = selectorsForKey;
					}
				};

				const select = key => {
					if ( ! storeSelectors[ key ] ) {
						// TODO: make this more functional in nature, e.g. by having
						// getStoreFromRegistry return a value, and making the side
						// effects from calling the function clearer?
						getStoreFromRegistry( key, props.registry, componentContext );
					}

					return storeSelectors[ key ];
				};

				const selectedProps = mapSelectToProps( select, props.ownProps ) || DEFAULT_MERGE_PROPS;

				// Complete the select for those stores which support it.
				onCompletes.forEach( onComplete => onComplete() );
				return selectedProps;
			}

			componentDidMount() {
				this.canRunSelection = true;

				// A state change may have occurred between the constructor and
				// mount of the component (e.g. during the wrapped components own
				// constructor), in which case selection should be rerun.
				if ( this.hasQueuedSelection ) {
					this.hasQueuedSelection = false;
					this.onStoreChange();
				}
			}

			componentWillUnmount() {
				this.canRunSelection = false;
				this.unsubscribe();
				Object.keys( this.onUnmounts ).forEach( key => this.onUnmounts[ key ]() );
			}

			shouldComponentUpdate( nextProps, nextState ) {
				// Cycle subscription if registry changes.
				const hasRegistryChanged = nextProps.registry !== this.props.registry;
				if ( hasRegistryChanged ) {
					this.unsubscribe();
					this.subscribe( nextProps.registry );
				}

				// Treat a registry change as equivalent to `ownProps`, to reflect
				// `mergeProps` to rendered component if and only if updated.
				const hasPropsChanged =
					hasRegistryChanged || ! isShallowEqual( this.props.ownProps, nextProps.ownProps );

				// Only render if props have changed or merge props have been updated
				// from the store subscriber.
				if ( this.state === nextState && ! hasPropsChanged ) {
					return false;
				}

				if ( hasPropsChanged ) {
					const nextMergeProps = this.getNextMergeProps( nextProps );
					if ( ! isShallowEqual( this.mergeProps, nextMergeProps ) ) {
						// If merge props change as a result of the incoming props,
						// they should be reflected as such in the upcoming render.
						// While side effects are discouraged in lifecycle methods,
						// this component is used heavily, and prior efforts to use
						// `getDerivedStateFromProps` had demonstrated miserable
						// performance.
						this.mergeProps = nextMergeProps;
					}

					// Regardless whether merge props are changing, fall through to
					// incur the render since the component will need to receive
					// the changed `ownProps`.
				}

				return true;
			}

			onStoreChange() {
				if ( ! this.canRunSelection ) {
					this.hasQueuedSelection = true;
					return;
				}

				const nextMergeProps = this.getNextMergeProps( this.props );
				if ( isShallowEqual( this.mergeProps, nextMergeProps ) ) {
					return;
				}

				this.mergeProps = nextMergeProps;

				// Schedule an update. Merge props are not assigned to state since
				// derivation of merge props from incoming props occurs within
				// shouldComponentUpdate, where setState is not allowed. setState
				// is used here instead of forceUpdate because forceUpdate bypasses
				// shouldComponentUpdate altogether, which isn't desireable if both
				// state and props change within the same render. Unfortunately,
				// this requires that next merge props are generated twice.
				this.setState( {} );
			}

			subscribe( registry ) {
				this.unsubscribe = registry.subscribe( this.onStoreChange );
			}

			render() {
				return <WrappedComponent { ...this.props.ownProps } { ...this.mergeProps } />;
			}
		}

		return ownProps => (
			<RegistryConsumer>
				{ registry => <ComponentWithSelect ownProps={ ownProps } registry={ registry } /> }
			</RegistryConsumer>
		)
	}, 'withSelect' );

export default withSelect;
