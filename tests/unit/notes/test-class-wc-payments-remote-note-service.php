<?php
/**
 * Class WC_Payments_Remote_Note_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\Rest_Request_Exception;

/**
 * Class WC_Payments_Remote_Note_Service tests.
 */
class WC_Payments_Remote_Note_Service_Test extends WP_UnitTestCase {

	/**
	 * Instance of WC_Payments_Remote_Note_Service under test.
	 *
	 * @var WC_Payments_Remote_Note_Service
	 */
	private $note_service;

	/**
	 * Mock of the data store.
	 *
	 * @var object
	 */
	private $mock_data_store;

	public function set_up() {
		parent::set_up();

		$this->mock_data_store = $this->getMockBuilder( WC_Data_Store::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_notes_with_name', 'create' ] )
			->getMock();
		$this->note_service    = new WC_Payments_Remote_Note_Service( $this->mock_data_store );
	}

	public function test_puts_simple_note() {
		$note_data = [
			'title'   => 'test',
			'content' => 'hello_world',
		];

		$this->mock_data_store
			->expects( $this->once() )
			->method( 'get_notes_with_name' )
			->willReturn( [] );

		$this->mock_data_store
			->expects( $this->once() )
			->method( 'create' )
			->with(
				$this->callback(
					function( $note ) use ( $note_data ) {
						return $note->get_name() === 'wc-payments-remote-notes-4cb476ca6ef0efd3ab9cf8d7e76d5083'
							&& $note->get_title() === $note_data['title']
							&& $note->get_content() === $note_data['content'];
					}
				)
			);

		$result = $this->note_service->put_note( $note_data );

		$this->assertTrue( $result );
	}

	public function test_puts_note_with_name_and_actions() {
		$note_data = [
			'name'    => 'test_name',
			'title'   => 'test',
			'content' => 'hello_world',
			'actions' => [
				[
					'label'   => 'Settings',
					'status'  => 'unactioned',
					'url'     => 'wcpay_settings',
					'primary' => true,
				],
			],
		];

		$this->mock_data_store
			->expects( $this->once() )
			->method( 'get_notes_with_name' )
			->willReturn( [] );

		$this->mock_data_store
			->expects( $this->once() )
			->method( 'create' )
			->with(
				$this->callback(
					function( $note ) use ( $note_data ) {
						return $note->get_name() === 'wc-payments-remote-notes-' . $note_data['name']
							&& $note->get_title() === $note_data['title']
							&& $note->get_content() === $note_data['content']
							&& 1 === count( $note->get_actions() );
					}
				)
			);

		$result = $this->note_service->put_note( $note_data );

		$this->assertTrue( $result );
	}

	public function test_does_not_put_duplicate_notes() {
		$note_data = [
			'title'   => 'test',
			'content' => 'hello_world',
		];

		$this->mock_data_store
			->expects( $this->once() )
			->method( 'get_notes_with_name' )
			->willReturn( [ 'not_empty' ] );

		$this->mock_data_store
			->expects( $this->never() )
			->method( 'create' );

		$result = $this->note_service->put_note( $note_data );

		$this->assertFalse( $result );
	}

	public function test_throws_on_malformed_data() {
		$note_data = [
			'title' => 'test',
		];

		$this->mock_data_store
			->expects( $this->never() )
			->method( 'get_notes_with_name' );

		$this->mock_data_store
			->expects( $this->never() )
			->method( 'create' );

		$this->expectException( Rest_Request_Exception::class );
		$this->note_service->put_note( $note_data );
	}
}
