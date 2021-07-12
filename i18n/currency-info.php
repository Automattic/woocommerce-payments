<?php
/**
 * Currency formatting information
 *
 * @package WooCommerce\Payments\i18n
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

$global_formats = [
	'ls_comma_dot_ltr'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'left_space',
	],
	'ls_comma_dot_rtl'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'rtl',
		'currency_pos' => 'left_space',
	],
	'ls_comma_space_ltr' => [
		'thousand_sep' => ' ',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'left_space',
	],
	'ls_dot_apos_ltr'    => [
		'thousand_sep' => '\'',
		'decimal_sep'  => '.',
		'direction'    => 'ltr',
		'currency_pos' => 'left_space',
	],
	'ls_dot_comma_ltr'   => [
		'thousand_sep' => ',',
		'decimal_sep'  => '.',
		'direction'    => 'ltr',
		'currency_pos' => 'left_space',
	],
	'ls_dot_comma_rtl'   => [
		'thousand_sep' => ',',
		'decimal_sep'  => '.',
		'direction'    => 'rtl',
		'currency_pos' => 'left_space',
	],
	'lx_comma_dot_ltr'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'left',
	],
	'lx_comma_dot_rtl'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'rtl',
		'currency_pos' => 'left',
	],
	'lx_comma_space_ltr' => [
		'thousand_sep' => ' ',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'left',
	],
	'lx_dot_comma_ltr'   => [
		'thousand_sep' => ',',
		'decimal_sep'  => '.',
		'direction'    => 'ltr',
		'currency_pos' => 'left',
	],
	'rs_comma_dot_ltr'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'right_space',
	],
	'rs_comma_dot_rtl'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'rtl',
		'currency_pos' => 'right_space',
	],
	'rs_comma_space_ltr' => [
		'thousand_sep' => ' ',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'right_space',
	],
	'rs_comma_space_rtl' => [
		'thousand_sep' => ' ',
		'decimal_sep'  => ',',
		'direction'    => 'rtl',
		'currency_pos' => 'right_space',
	],
	'rs_dot_apos_ltr'    => [
		'thousand_sep' => '\'',
		'decimal_sep'  => '.',
		'direction'    => 'ltr',
		'currency_pos' => 'right_space',
	],
	'rs_dot_comma_ltr'   => [
		'thousand_sep' => ',',
		'decimal_sep'  => '.',
		'direction'    => 'ltr',
		'currency_pos' => 'right_space',
	],
	'rs_dot_comma_rtl'   => [
		'thousand_sep' => ',',
		'decimal_sep'  => '.',
		'direction'    => 'rtl',
		'currency_pos' => 'right_space',
	],
	'rx_comma_dot_ltr'   => [
		'thousand_sep' => '.',
		'decimal_sep'  => ',',
		'direction'    => 'ltr',
		'currency_pos' => 'right',
	],
	'rx_dot_comma_ltr'   => [
		'thousand_sep' => ',',
		'decimal_sep'  => '.',
		'direction'    => 'ltr',
		'currency_pos' => 'right',
	],
];

return [
	'AED' => [
		'ar_AE'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'AFN' => [
		'fa_AF'   => $global_formats['ls_comma_dot_rtl'],
		'default' => $global_formats['ls_comma_dot_rtl'],
		'ps_AF'   => $global_formats['rs_comma_dot_rtl'],
		'uz_AF'   => $global_formats['rs_comma_dot_ltr'],
	],
	'ALL' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'sq_AL'   => $global_formats['rs_comma_space_ltr'],
	],
	'AMD' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'hy_AM'   => $global_formats['rs_comma_space_ltr'],
	],
	'ANG' => [
		'en_SX'   => $global_formats['lx_dot_comma_ltr'],
		'nl_CW'   => $global_formats['ls_dot_comma_ltr'],
		'nl_SX'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['ls_dot_comma_ltr'],
	],
	'AOA' => [
		'pt_AO'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
	],
	'ARS' => [
		'es_AR'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'AUD' => [
		'en_AU'   => $global_formats['lx_dot_comma_ltr'],
		'en_CC'   => $global_formats['lx_dot_comma_ltr'],
		'en_CX'   => $global_formats['lx_dot_comma_ltr'],
		'en_KI'   => $global_formats['lx_dot_comma_ltr'],
		'en_NF'   => $global_formats['lx_dot_comma_ltr'],
		'en_NR'   => $global_formats['lx_dot_comma_ltr'],
		'en_TV'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'AWG' => [
		'nl_AW'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'AZN' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'az_AZ'   => $global_formats['rs_comma_dot_ltr'],
	],
	'BAM' => [
		'hr_BA'      => $global_formats['rs_comma_dot_ltr'],
		'sr_Latn_BA' => $global_formats['rs_comma_dot_ltr'],
		'default'    => $global_formats['rs_comma_dot_ltr'],
		'bs_BA'      => $global_formats['rs_comma_dot_ltr'],
	],
	'BBD' => [
		'en_BB'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'BDT' => [
		'default' => $global_formats['rx_dot_comma_ltr'],
		'bn_BD'   => $global_formats['rx_dot_comma_ltr'],
	],
	'BGN' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'bg_BG'   => $global_formats['rs_comma_space_ltr'],
	],
	'BHD' => [
		'ar_BH'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'BIF' => [
		'en_BI'   => $global_formats['lx_comma_dot_ltr'],
		'fr_BI'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['lx_comma_dot_ltr'],
		'rn_BI'   => $global_formats['rx_comma_dot_ltr'],
	],
	'BMD' => [
		'en_BM'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'BND' => [
		'ms_BN'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'BOB' => [
		'es_BO'   => $global_formats['lx_comma_dot_ltr'],
		'qu_BO'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['lx_comma_dot_ltr'],
	],
	'BRL' => [
		'default' => $global_formats['ls_comma_dot_ltr'],
		'pt_BR'   => $global_formats['ls_comma_dot_ltr'],
	],
	'BSD' => [
		'en_BS'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'BTN' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'dz_BT'   => $global_formats['lx_dot_comma_ltr'],
	],
	'BWP' => [
		'en_BW'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'BYN' => [
		'ru_BY'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
		'be_BY'   => $global_formats['rs_comma_space_ltr'],
	],
	'BZD' => [
		'en_BZ'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'CAD' => [
		'en_CA'   => $global_formats['lx_dot_comma_ltr'],
		'fr_CA'   => $global_formats['rs_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'CDF' => [
		'fr_CD'   => $global_formats['rs_comma_dot_ltr'],
		'sw_CD'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_ltr'],
		'ln_CD'   => $global_formats['rs_comma_dot_ltr'],
	],
	'CHF' => [
		'de_CH'   => $global_formats['ls_dot_apos_ltr'],
		'de_LI'   => $global_formats['ls_dot_apos_ltr'],
		'fr_CH'   => $global_formats['rs_dot_apos_ltr'],
		'gsw_LI'  => $global_formats['rs_dot_apos_ltr'],
		'it_CH'   => $global_formats['ls_dot_apos_ltr'],
		'default' => $global_formats['rs_dot_apos_ltr'],
		'gsw_CH'  => $global_formats['rs_dot_apos_ltr'],
		'rm_CH'   => $global_formats['rs_dot_apos_ltr'],
	],
	'CLP' => [
		'es_CL'   => $global_formats['lx_comma_dot_ltr'],
		'default' => $global_formats['lx_comma_dot_ltr'],
	],
	'CNY' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'bo_CN'   => $global_formats['ls_dot_comma_ltr'],
		'ug_CN'   => $global_formats['lx_dot_comma_ltr'],
		'zh_CN'   => $global_formats['lx_dot_comma_ltr'],
	],
	'COP' => [
		'es_CO'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'CRC' => [
		'es_CR'   => $global_formats['lx_comma_space_ltr'],
		'default' => $global_formats['lx_comma_space_ltr'],
	],
	'CUC' => [
		'es_CU'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'CVE' => [
		'pt_CV'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
	],
	'CZK' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'cs_CZ'   => $global_formats['rs_comma_space_ltr'],
	],
	'DJF' => [
		'ar_DJ'   => $global_formats['rs_comma_dot_rtl'],
		'fr_DJ'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'DKK' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'da_DK'   => $global_formats['rs_comma_dot_ltr'],
		'fo_FO'   => $global_formats['rs_comma_dot_ltr'],
		'kl_GL'   => $global_formats['lx_comma_dot_ltr'],
	],
	'DOP' => [
		'es_DO'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'DZD' => [
		'ar_DZ'   => $global_formats['ls_comma_dot_rtl'],
		'fr_DZ'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_rtl'],
	],
	'EGP' => [
		'ar_EG'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'ERN' => [
		'ar_ER'   => $global_formats['rs_dot_comma_rtl'],
		'en_ER'   => $global_formats['lx_dot_comma_ltr'],
		'ti_ER'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'ETB' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'am_ET'   => $global_formats['lx_dot_comma_ltr'],
	],
	'EUR' => [
		'ca_AD'      => $global_formats['rs_comma_space_ltr'],
		'de_AT'      => $global_formats['ls_comma_space_ltr'],
		'de_BE'      => $global_formats['rs_comma_space_ltr'],
		'de_LU'      => $global_formats['rs_comma_space_ltr'],
		'el_CY'      => $global_formats['rs_comma_space_ltr'],
		'en_IE'      => $global_formats['lx_comma_space_ltr'],
		'en_MT'      => $global_formats['lx_comma_space_ltr'],
		'es_EA'      => $global_formats['rs_comma_space_ltr'],
		'es_IC'      => $global_formats['rs_comma_space_ltr'],
		'fr_BE'      => $global_formats['rs_comma_space_ltr'],
		'fr_BL'      => $global_formats['rs_comma_space_ltr'],
		'fr_GF'      => $global_formats['rs_comma_space_ltr'],
		'fr_GP'      => $global_formats['rs_comma_space_ltr'],
		'fr_LU'      => $global_formats['rs_comma_space_ltr'],
		'fr_MC'      => $global_formats['rs_comma_space_ltr'],
		'fr_MF'      => $global_formats['rs_comma_space_ltr'],
		'fr_MQ'      => $global_formats['rs_comma_space_ltr'],
		'fr_PM'      => $global_formats['rs_comma_space_ltr'],
		'fr_RE'      => $global_formats['rs_comma_space_ltr'],
		'fr_YT'      => $global_formats['rs_comma_space_ltr'],
		'it_SM'      => $global_formats['rs_comma_space_ltr'],
		'it_VA'      => $global_formats['rs_comma_space_ltr'],
		'nl_BE'      => $global_formats['ls_comma_space_ltr'],
		'pt_PT'      => $global_formats['rs_comma_space_ltr'],
		'sq_XK'      => $global_formats['rs_comma_space_ltr'],
		'sr_Latn_ME' => $global_formats['rs_comma_space_ltr'],
		'sr_Latn_XK' => $global_formats['rs_comma_space_ltr'],
		'sv_AX'      => $global_formats['rs_comma_space_ltr'],
		'sv_FI'      => $global_formats['rs_comma_space_ltr'],
		'tr_CY'      => $global_formats['lx_comma_space_ltr'],
		'default'    => $global_formats['rs_comma_space_ltr'],
		'ast_ES'     => $global_formats['rs_comma_space_ltr'],
		'ca_ES'      => $global_formats['rs_comma_space_ltr'],
		'de_DE'      => $global_formats['rs_comma_space_ltr'],
		'el_GR'      => $global_formats['rs_comma_space_ltr'],
		'es_ES'      => $global_formats['rs_comma_space_ltr'],
		'et_EE'      => $global_formats['rs_comma_space_ltr'],
		'eu_ES'      => $global_formats['rs_comma_space_ltr'],
		'fi_FI'      => $global_formats['rs_comma_space_ltr'],
		'fr_FR'      => $global_formats['rs_comma_space_ltr'],
		'fy_NL'      => $global_formats['ls_comma_space_ltr'],
		'ga_IE'      => $global_formats['lx_comma_space_ltr'],
		'gl_ES'      => $global_formats['rs_comma_space_ltr'],
		'it_IT'      => $global_formats['rs_comma_space_ltr'],
		'lb_LU'      => $global_formats['rs_comma_space_ltr'],
		'lt_LT'      => $global_formats['rs_comma_space_ltr'],
		'lv_LV'      => $global_formats['rs_comma_space_ltr'],
		'mt_MT'      => $global_formats['lx_comma_space_ltr'],
		'nl_NL'      => $global_formats['ls_comma_space_ltr'],
		'sk_SK'      => $global_formats['rs_comma_space_ltr'],
		'sl_SI'      => $global_formats['rs_comma_space_ltr'],
	],
	'FJD' => [
		'en_FJ'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'FKP' => [
		'en_FK'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'GBP' => [
		'en_GB'   => $global_formats['lx_dot_comma_ltr'],
		'en_IM'   => $global_formats['lx_dot_comma_ltr'],
		'en_JE'   => $global_formats['lx_dot_comma_ltr'],
		'ga_GB'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'cy_GB'   => $global_formats['lx_dot_comma_ltr'],
		'gd_GB'   => $global_formats['lx_dot_comma_ltr'],
		'gv_IM'   => $global_formats['lx_dot_comma_ltr'],
	],
	'GEL' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'ka_GE'   => $global_formats['rs_comma_space_ltr'],
		'os_GE'   => $global_formats['ls_comma_space_ltr'],
	],
	'GHS' => [
		'en_GH'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'ak_GH'   => $global_formats['lx_dot_comma_ltr'],
		'ee_GH'   => $global_formats['lx_dot_comma_ltr'],
	],
	'GIP' => [
		'en_GI'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'GMD' => [
		'en_GM'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'GNF' => [
		'fr_GN'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
	],
	'GTQ' => [
		'es_GT'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'GYD' => [
		'en_GY'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'HKD' => [
		'en_HK'      => $global_formats['lx_dot_comma_ltr'],
		'zh_Hant_HK' => $global_formats['lx_dot_comma_ltr'],
		'default'    => $global_formats['lx_dot_comma_ltr'],
	],
	'HNL' => [
		'es_HN'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'HRK' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'hr_HR'   => $global_formats['rs_comma_dot_ltr'],
	],
	'HUF' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'hu_HU'   => $global_formats['rs_comma_space_ltr'],
	],
	'IDR' => [
		'default' => $global_formats['lx_comma_dot_ltr'],
		'id_ID'   => $global_formats['lx_comma_dot_ltr'],
	],
	'ILS' => [
		'ar_IL'   => $global_formats['rs_dot_comma_rtl'],
		'ar_PS'   => $global_formats['rs_dot_comma_rtl'],
		'default' => $global_formats['rs_dot_comma_rtl'],
		'he_IL'   => $global_formats['rs_dot_comma_rtl'],
	],
	'INR' => [
		'bn_IN'   => $global_formats['rx_dot_comma_ltr'],
		'en_IN'   => $global_formats['lx_dot_comma_ltr'],
		'ne_IN'   => $global_formats['ls_dot_comma_ltr'],
		'ur_IN'   => $global_formats['ls_dot_comma_rtl'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'as_IN'   => $global_formats['ls_dot_comma_ltr'],
		'dz_BT'   => $global_formats['lx_dot_comma_ltr'],
		'gu_IN'   => $global_formats['lx_dot_comma_ltr'],
		'hi_IN'   => $global_formats['lx_dot_comma_ltr'],
		'kok_IN'  => $global_formats['ls_dot_comma_ltr'],
		'mai_IN'  => $global_formats['ls_dot_comma_ltr'],
		'ml_IN'   => $global_formats['lx_dot_comma_ltr'],
		'mr_IN'   => $global_formats['lx_dot_comma_ltr'],
		'or_IN'   => $global_formats['lx_dot_comma_ltr'],
		'sa_IN'   => $global_formats['lx_dot_comma_ltr'],
		'sd_PK'   => $global_formats['rs_dot_comma_ltr'],
		'ta_IN'   => $global_formats['ls_dot_comma_ltr'],
		'te_IN'   => $global_formats['lx_dot_comma_ltr'],
	],
	'IQD' => [
		'ar_IQ'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
		'ckb_IQ'  => $global_formats['rs_comma_dot_rtl'],
	],
	'IRR' => [
		'default' => $global_formats['lx_comma_dot_rtl'],
		'fa_IR'   => $global_formats['lx_comma_dot_rtl'],
	],
	'ISK' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'is_IS'   => $global_formats['rs_comma_dot_ltr'],
	],
	'JMD' => [
		'en_JM'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'JOD' => [
		'ar_JO'   => $global_formats['rs_comma_dot_rtl'],
		'ar_PS'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'JPY' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'ja_JP'   => $global_formats['lx_dot_comma_ltr'],
	],
	'KES' => [
		'en_KE'   => $global_formats['lx_dot_comma_ltr'],
		'sw_KE'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'KGS' => [
		'ru_KG'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
		'ky_KG'   => $global_formats['rs_comma_space_ltr'],
	],
	'KHR' => [
		'default' => $global_formats['rx_comma_dot_ltr'],
		'km_KH'   => $global_formats['rx_comma_dot_ltr'],
		'kn_IN'   => $global_formats['lx_comma_dot_ltr'],
	],
	'KMF' => [
		'ar_KM'   => $global_formats['rs_comma_dot_rtl'],
		'fr_KM'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'KPW' => [
		'ko_KP'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'KRW' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'ko_KR'   => $global_formats['lx_dot_comma_ltr'],
	],
	'KWD' => [
		'ar_KW'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'KYD' => [
		'en_KY'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'KZT' => [
		'ru_KZ'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
		'kk_KZ'   => $global_formats['rs_comma_space_ltr'],
	],
	'LAK' => [
		'default' => $global_formats['lx_comma_dot_ltr'],
		'lo_LA'   => $global_formats['lx_comma_dot_ltr'],
	],
	'LBP' => [
		'ar_LB'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'LKR' => [
		'ta_LK'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'si_LK'   => $global_formats['lx_dot_comma_ltr'],
	],
	'LRD' => [
		'en_LR'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'LSL' => [
		'en_LS'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'LYD' => [
		'ar_LY'   => $global_formats['ls_comma_dot_rtl'],
		'default' => $global_formats['ls_comma_dot_rtl'],
	],
	'MAD' => [
		'ar_EH'   => $global_formats['ls_comma_dot_rtl'],
		'ar_MA'   => $global_formats['ls_comma_dot_rtl'],
		'fr_MA'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_rtl'],
		'tzm_MA'  => $global_formats['rs_comma_dot_ltr'],
	],
	'MDL' => [
		'ro_MD'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_ltr'],
	],
	'MGA' => [
		'en_MG'   => $global_formats['lx_dot_comma_ltr'],
		'fr_MG'   => $global_formats['rs_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'mg_MG'   => $global_formats['ls_dot_comma_ltr'],
	],
	'MKD' => [
		'sq_MK'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_ltr'],
		'mk_MK'   => $global_formats['rs_comma_dot_ltr'],
	],
	'MMK' => [
		'default' => $global_formats['rs_dot_comma_ltr'],
		'my_MM'   => $global_formats['rs_dot_comma_ltr'],
	],
	'MNT' => [
		'default' => $global_formats['ls_dot_comma_ltr'],
		'mn_MN'   => $global_formats['ls_dot_comma_ltr'],
	],
	'MOP' => [
		'pt_MO'      => $global_formats['rs_dot_comma_ltr'],
		'zh_Hant_MO' => $global_formats['lx_dot_comma_ltr'],
		'default'    => $global_formats['rs_dot_comma_ltr'],
	],
	'MRU' => [
		'ar_MR'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'MUR' => [
		'en_MU'   => $global_formats['lx_dot_comma_ltr'],
		'fr_MU'   => $global_formats['rs_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'MVR' => [
		'default' => null,
	],
	'MWK' => [
		'en_MW'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'MXN' => [
		'es_MX'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'MYR' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'ms_MY'   => $global_formats['lx_dot_comma_ltr'],
	],
	'MZN' => [
		'pt_MZ'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
	],
	'NAD' => [
		'en_NA'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'NGN' => [
		'en_NG'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'yo_NG'   => $global_formats['lx_dot_comma_ltr'],
	],
	'NIO' => [
		'es_NI'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'NOK' => [
		'nb_SJ'   => $global_formats['ls_comma_space_ltr'],
		'default' => $global_formats['ls_comma_space_ltr'],
		'nb_NO'   => $global_formats['ls_comma_space_ltr'],
		'nn_NO'   => $global_formats['rs_comma_space_ltr'],
		'se_NO'   => $global_formats['rs_comma_space_ltr'],
	],
	'NPR' => [
		'default' => $global_formats['ls_dot_comma_ltr'],
		'ne_NP'   => $global_formats['ls_dot_comma_ltr'],
	],
	'NZD' => [
		'en_CK'   => $global_formats['lx_dot_comma_ltr'],
		'en_NU'   => $global_formats['lx_dot_comma_ltr'],
		'en_NZ'   => $global_formats['lx_dot_comma_ltr'],
		'en_PN'   => $global_formats['lx_dot_comma_ltr'],
		'en_TK'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'mi_NZ'   => $global_formats['ls_dot_comma_ltr'],
	],
	'OMR' => [
		'ar_OM'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'PEN' => [
		'es_PE'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['ls_dot_comma_ltr'],
		'qu_PE'   => $global_formats['ls_dot_comma_ltr'],
	],
	'PGK' => [
		'en_PG'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'PHP' => [
		'en_PH'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'ceb_PH'  => $global_formats['lx_dot_comma_ltr'],
		'fil_PH'  => $global_formats['lx_dot_comma_ltr'],
	],
	'PKR' => [
		'en_PK'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'ur_PK'   => $global_formats['ls_dot_comma_rtl'],
	],
	'PLN' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'pl_PL'   => $global_formats['rs_comma_space_ltr'],
	],
	'PYG' => [
		'es_PY'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'QAR' => [
		'ar_QA'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'RON' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'ro_RO'   => $global_formats['rs_comma_dot_ltr'],
	],
	'RSD' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'sr_RS'   => $global_formats['rs_comma_dot_ltr'],
	],
	'RUB' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'ce_RU'   => $global_formats['rs_comma_space_ltr'],
		'ru_RU'   => $global_formats['rs_comma_space_ltr'],
		'sah_RU'  => $global_formats['rs_comma_space_ltr'],
		'tt_RU'   => $global_formats['rs_comma_space_ltr'],
	],
	'RWF' => [
		'en_RW'   => $global_formats['lx_comma_dot_ltr'],
		'fr_RW'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['lx_comma_dot_ltr'],
		'rw_RW'   => $global_formats['ls_comma_dot_ltr'],
	],
	'SAR' => [
		'ar_SA'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'SBD' => [
		'en_SB'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'SCR' => [
		'en_SC'   => $global_formats['lx_comma_space_ltr'],
		'fr_SC'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['lx_comma_space_ltr'],
	],
	'SDG' => [
		'ar_SD'   => $global_formats['rs_comma_dot_rtl'],
		'en_SD'   => $global_formats['lx_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'SEK' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'sv_SE'   => $global_formats['rs_comma_space_ltr'],
	],
	'SGD' => [
		'en_SG'   => $global_formats['lx_dot_comma_ltr'],
		'ms_SG'   => $global_formats['lx_dot_comma_ltr'],
		'ta_SG'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'SHP' => [
		'en_SH'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'SLL' => [
		'en_SL'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'SOS' => [
		'ar_SO'   => $global_formats['rs_dot_comma_rtl'],
		'default' => $global_formats['rs_dot_comma_rtl'],
		'so_SO'   => $global_formats['lx_dot_comma_ltr'],
	],
	'SRD' => [
		'nl_SR'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'SSP' => [
		'en_SS'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'STN' => [
		'pt_ST'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
	],
	'SYP' => [
		'ar_SY'   => $global_formats['rs_comma_dot_rtl'],
		'fr_SY'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'SZL' => [
		'en_SZ'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'THB' => [
		'default' => $global_formats['lx_dot_comma_ltr'],
		'th_TH'   => $global_formats['lx_dot_comma_ltr'],
	],
	'TJS' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'tg_TJ'   => $global_formats['rs_comma_space_ltr'],
	],
	'TMT' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'tk_TM'   => $global_formats['rs_comma_space_ltr'],
	],
	'TND' => [
		'ar_TN'   => $global_formats['ls_comma_dot_rtl'],
		'fr_TN'   => $global_formats['rs_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_rtl'],
	],
	'TOP' => [
		'en_TO'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'to_TO'   => $global_formats['ls_dot_comma_ltr'],
	],
	'TRY' => [
		'default' => $global_formats['lx_comma_dot_ltr'],
		'tr_TR'   => $global_formats['lx_comma_dot_ltr'],
	],
	'TTD' => [
		'en_TT'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'TWD' => [
		'zh_Hant' => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'TZS' => [
		'en_TZ'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'sw_TZ'   => $global_formats['ls_dot_comma_ltr'],
	],
	'UAH' => [
		'ru_UA'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
		'uk_UA'   => $global_formats['rs_comma_space_ltr'],
	],
	'UGX' => [
		'en_UG'   => $global_formats['lx_dot_comma_ltr'],
		'sw_UG'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'USD' => [
		'en_AS'   => $global_formats['lx_dot_comma_ltr'],
		'en_DG'   => $global_formats['lx_dot_comma_ltr'],
		'en_FM'   => $global_formats['lx_dot_comma_ltr'],
		'en_GU'   => $global_formats['lx_dot_comma_ltr'],
		'en_IO'   => $global_formats['lx_dot_comma_ltr'],
		'en_MH'   => $global_formats['lx_dot_comma_ltr'],
		'en_MP'   => $global_formats['lx_dot_comma_ltr'],
		'en_PR'   => $global_formats['lx_dot_comma_ltr'],
		'en_PW'   => $global_formats['lx_dot_comma_ltr'],
		'en_TC'   => $global_formats['lx_dot_comma_ltr'],
		'en_UM'   => $global_formats['lx_dot_comma_ltr'],
		'en_VG'   => $global_formats['lx_dot_comma_ltr'],
		'en_VI'   => $global_formats['lx_dot_comma_ltr'],
		'en_ZW'   => $global_formats['lx_dot_comma_ltr'],
		'es_EC'   => $global_formats['lx_dot_comma_ltr'],
		'es_PA'   => $global_formats['lx_dot_comma_ltr'],
		'es_PR'   => $global_formats['lx_dot_comma_ltr'],
		'es_SV'   => $global_formats['lx_dot_comma_ltr'],
		'es_US'   => $global_formats['lx_dot_comma_ltr'],
		'fr_HT'   => $global_formats['rs_dot_comma_ltr'],
		'nl_BQ'   => $global_formats['ls_dot_comma_ltr'],
		'pt_TL'   => $global_formats['rs_dot_comma_ltr'],
		'qu_EC'   => $global_formats['ls_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
		'en_US'   => $global_formats['lx_dot_comma_ltr'],
		'haw_US'  => $global_formats['lx_dot_comma_ltr'],
		'nd_ZW'   => $global_formats['lx_dot_comma_ltr'],
		'sn_ZW'   => $global_formats['ls_dot_comma_ltr'],
	],
	'UYU' => [
		'es_UY'   => $global_formats['ls_comma_dot_ltr'],
		'default' => $global_formats['ls_comma_dot_ltr'],
	],
	'UZS' => [
		'default' => $global_formats['rs_comma_space_ltr'],
		'uz_AF'   => $global_formats['rs_comma_space_ltr'],
	],
	'VES' => [
		'es_VE'   => $global_formats['lx_comma_dot_ltr'],
		'default' => $global_formats['lx_comma_dot_ltr'],
	],
	'VND' => [
		'default' => $global_formats['rs_comma_dot_ltr'],
		'vi_VN'   => $global_formats['rs_comma_dot_ltr'],
	],
	'VUV' => [
		'en_VU'   => $global_formats['lx_dot_comma_ltr'],
		'fr_VU'   => $global_formats['rs_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'WST' => [
		'en_WS'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'XAF' => [
		'ar_TD'   => $global_formats['rs_comma_space_rtl'],
		'en_CM'   => $global_formats['lx_comma_space_ltr'],
		'es_GQ'   => $global_formats['lx_comma_space_ltr'],
		'fr_CF'   => $global_formats['rs_comma_space_ltr'],
		'fr_CG'   => $global_formats['rs_comma_space_ltr'],
		'fr_CM'   => $global_formats['rs_comma_space_ltr'],
		'fr_GA'   => $global_formats['rs_comma_space_ltr'],
		'fr_GQ'   => $global_formats['rs_comma_space_ltr'],
		'fr_TD'   => $global_formats['rs_comma_space_ltr'],
		'pt_GQ'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
		'sg_CF'   => $global_formats['lx_comma_space_ltr'],
	],
	'XCD' => [
		'en_AG'   => $global_formats['lx_dot_comma_ltr'],
		'en_AI'   => $global_formats['lx_dot_comma_ltr'],
		'en_DM'   => $global_formats['lx_dot_comma_ltr'],
		'en_GD'   => $global_formats['lx_dot_comma_ltr'],
		'en_KN'   => $global_formats['lx_dot_comma_ltr'],
		'en_LC'   => $global_formats['lx_dot_comma_ltr'],
		'en_MS'   => $global_formats['lx_dot_comma_ltr'],
		'en_VC'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
	'XOF' => [
		'fr_BF'   => $global_formats['rs_comma_space_ltr'],
		'fr_BJ'   => $global_formats['rs_comma_space_ltr'],
		'fr_CI'   => $global_formats['rs_comma_space_ltr'],
		'fr_ML'   => $global_formats['rs_comma_space_ltr'],
		'fr_NE'   => $global_formats['rs_comma_space_ltr'],
		'fr_SN'   => $global_formats['rs_comma_space_ltr'],
		'fr_TG'   => $global_formats['rs_comma_space_ltr'],
		'pt_GW'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
		'dyo_SN'  => $global_formats['rs_comma_space_ltr'],
		'wo_SN'   => $global_formats['ls_comma_space_ltr'],
	],
	'XPF' => [
		'fr_NC'   => $global_formats['rs_comma_space_ltr'],
		'fr_PF'   => $global_formats['rs_comma_space_ltr'],
		'fr_WF'   => $global_formats['rs_comma_space_ltr'],
		'default' => $global_formats['rs_comma_space_ltr'],
	],
	'YER' => [
		'ar_YE'   => $global_formats['rs_comma_dot_rtl'],
		'default' => $global_formats['rs_comma_dot_rtl'],
	],
	'ZAR' => [
		'en_LS'   => $global_formats['lx_comma_space_ltr'],
		'en_NA'   => $global_formats['lx_comma_space_ltr'],
		'en_ZA'   => $global_formats['lx_comma_space_ltr'],
		'default' => $global_formats['lx_comma_space_ltr'],
		'af_ZA'   => $global_formats['lx_comma_space_ltr'],
		'xh_ZA'   => $global_formats['lx_comma_space_ltr'],
		'zu_ZA'   => $global_formats['lx_comma_space_ltr'],
	],
	'ZMW' => [
		'en_ZM'   => $global_formats['lx_dot_comma_ltr'],
		'default' => $global_formats['lx_dot_comma_ltr'],
	],
];
