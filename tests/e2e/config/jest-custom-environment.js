const { TestEnvironment } = require( 'jest-environment-puppeteer' );
require( 'jest-circus' );

class JestCustomEnvironment extends TestEnvironment {
	async handleTestEvent( event, state ) {
		if ( event.name === 'test_fn_failure' && this.global.page ) {
			const testDescription = state.currentlyRunningTest.name.replace(
				/[":<>\|*?]/g,
				''
			);

			await this.global.page.screenshot( {
				path: `./screenshots/${ testDescription }.png`,
				fullPage: true,
			} );
		}
	}
}

module.exports = JestCustomEnvironment;
