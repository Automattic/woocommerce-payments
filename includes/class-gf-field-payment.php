<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Wcpayment extends GF_Field {
	public $type = 'wcpayment';

	public $choices = [
		[ 'text' => 'Food Choice 1' ],
		[ 'text' => 'Food Choice 2' ],
		[ 'text' => 'Food Choice 3' ],
	];

	private $delivery_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

	public function get_form_editor_field_title() {
		return esc_attr__('Food Delivery', 'txtdomain');
	}

	public function get_form_editor_button() {
		return [
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		];
	}

	public function get_form_editor_field_settings() {
		return [
			'encrypt_setting',
			'product_field_setting',
			'label_setting',
			'choices_setting',
			'description_setting',
			'rules_setting',
			'error_message_setting',
			'css_class_setting',
			'conditional_logic_field_setting',
		];
	}

	public function is_value_submission_array() {
		return true;
	}

	public function get_field_input($form, $value = '', $entry = null) {
		if ($this->is_form_editor()) {
			return '';
		}

		$id = (int) $this->id;

		if ($this->is_entry_detail()) {
			$table_value = maybe_unserialize($value);
		} else {
			$table_value = $this->translateValueArray($value);
		}

		$table = '<table class="delivery-table"><tbody><tr>';
		$table .= '<th>' . __('WCPay Payment', 'txtdomain') . '</th>';

		$table .= '</tr>';


		$table .= '</tbody></table>';

		return $table;
	}

	private function translateValueArray($value) {
		if (empty($value)) {
			return [];
		}
		$table_value = [];
		$counter = 0;
		foreach ($this->choices as $course) {
			foreach ($this->delivery_days as $day) {
				$table_value[$course['text']][$day] = $value[$counter++];
			}
		}
		return $table_value;
	}

	public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
		if (empty($value)) {
			$value = '';
		} else {
			$table_value = $this->translateValueArray($value);
			$value = serialize($table_value);
		}
		return $value;
	}

	private function prettyListOutput($value) {
		$str = '<ul>';
		foreach ($value as $course => $days) {
			$week = '';
			foreach ($days as $day => $delivery_number) {
				if (!empty($delivery_number)) {
					$week .= '<li>' . $day . ': ' . $delivery_number . '</li>';
				}
			}
			// Only add week if there were any requests at all
			if (!empty($week)) {
				$str .= '<li><h3>' . $course . '</h3><ul class="days">' . $week . '</ul></li>';
			}
		}
		$str .= '</ul>';
		return $str;
	}

	public function get_value_entry_list($value, $entry, $field_id, $columns, $form) {
		return __('Enter details to see delivery details', 'txtdomain');
	}

	public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
		$value = maybe_unserialize($value);
		if (empty($value)) {
			return $value;
		}
		$str = $this->prettyListOutput($value);
		return $str;
	}

	public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br) {
		return $this->prettyListOutput($value);
	}

	public function is_value_submission_empty($form_id) {
		$value = rgpost('input_' . $this->id);
		foreach ($value as $input) {
			if (strlen(trim($input)) > 0) {
				return false;
			}
		}
		return true;
	}

}

GF_Fields::register( new GF_Field_Wcpayment() );
