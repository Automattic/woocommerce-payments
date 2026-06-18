<?php
/**
 * Class Express_Checkout_Hong_Kong_States
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Express_Checkout_Hong_Kong_States
 *
 * Maps Hong Kong regions, districts and sub-districts (equivalent to WC states) to the three
 * WooCommerce Hong Kong state keys (`HONG KONG`, `KOWLOON`, `NEW TERRITORIES`). This is necessary
 * because of bugs in Apple Pay/Google Pay that deliver Hong Kong address data inconsistently:
 * the region (e.g. "New Territories") may arrive in the `state` or `postcode` field, or be dropped
 * entirely — in which case only the district survives in the `city` field. By mapping every
 * district and sub-district to its parent region we can recover a valid WC state from whichever
 * field carries the information.
 *
 * More info in pc4etw-bY-p2.
 *
 * Note: The original district list was kindly sourced from the WooCommerce Stripe Payment Gateway
 * implementation, which addresses the same issue in that plugin.
 * See https://github.com/woocommerce/woocommerce-gateway-stripe/pull/1593.
 *
 * @since x.x.x
 */
class Express_Checkout_Hong_Kong_States {
	/**
	 * WooCommerce Hong Kong state key for Hong Kong Island.
	 */
	const HONG_KONG_ISLAND = 'HONG KONG';

	/**
	 * WooCommerce Hong Kong state key for Kowloon.
	 */
	const KOWLOON = 'KOWLOON';

	/**
	 * WooCommerce Hong Kong state key for the New Territories.
	 */
	const NEW_TERRITORIES = 'NEW TERRITORIES';

	/**
	 * A map of Hong Kong regions, districts and sub-districts (English + 中文) to the WooCommerce
	 * Hong Kong state key they belong to.
	 *
	 * Source: https://www.rvd.gov.hk/doc/tc/hkpr13/06.pdf.
	 */
	// phpcs:disable
	const REGIONS = [
		self::HONG_KONG_ISLAND => [
			'hong kong',
			'hong kong island',
			'港島',

			'central and western',
			'中西區',
			'kennedy town',
			'shek tong tsui',
			'sai ying pun',
			'sheung wan',
			'central',
			'admiralty',
			'mid-levels',
			'peak',
			'堅尼地城',
			'石塘咀',
			'西營盤',
			'上環',
			'中環',
			'金鐘',
			'半山區',
			'山頂',

			'wan chai',
			'灣仔',
			'causeway bay',
			'happy valley',
			'tai hang',
			'so kon po',
			"jardine's lookout",
			'銅鑼灣',
			'跑馬地',
			'大坑',
			'掃桿埔',
			'渣甸山',

			'eastern',
			'東區',
			'tin hau',
			'braemar hill',
			'north point',
			'quarry bay',
			'sai wan ho',
			'shau kei wan',
			'chai wan',
			'siu sai wan',
			'天后',
			'寶馬山',
			'北角',
			'鰂魚涌',
			'西灣河',
			'筲箕灣',
			'柴灣',
			'小西灣',

			'southern',
			'南區',
			'pok fu lam',
			'aberdeen',
			'ap lei chau',
			'wong chuk hang',
			'shouson hill',
			'repulse bay',
			'chung hom kok',
			'stanley',
			'tai tam',
			'shek o',
			'薄扶林',
			'香港仔',
			'鴨脷洲',
			'黃竹坑',
			'壽臣山',
			'淺水灣',
			'舂磡角',
			'赤柱',
			'大潭',
			'石澳',
		],
		self::KOWLOON => [
			'kowloon',
			'九龍',

			'yau tsim mong',
			'油尖旺',
			'tsim sha tsui',
			'yau ma tei',
			'west kowloon reclamation',
			"king's park, mong kok",
			'tai kok tsui',
			'尖沙咀',
			'油麻地',
			'西九龍填海區',
			'京士柏',
			'旺角',
			'大角咀',

			'sham shui po',
			'深水埗',
			'mei foo',
			'lai chi kok',
			'cheung sha wan',
			'shek kip mei',
			'yau yat tsuen',
			'tai wo ping',
			'stonecutters island',
			'美孚',
			'荔枝角',
			'長沙灣',
			'石硤尾',
			'又一村',
			'大窩坪',
			'昂船洲',

			'kowloon city',
			'九龍城',
			'hung hom',
			'to kwa wan',
			'ma tau kok',
			'ma tau wai',
			'kai tak',
			'ho man tin',
			'kowloon tong',
			'beacon hill',
			'紅磡',
			'土瓜灣',
			'馬頭角',
			'馬頭圍',
			'啟德',
			'何文田',
			'九龍塘',
			'筆架山',

			'wong tai sin',
			'黃大仙',
			'san po kong',
			'tung tau',
			'wang tau hom',
			'lok fu',
			'diamond hill',
			'tsz wan shan',
			'ngau chi wan',
			'新蒲崗',
			'東頭',
			'橫頭磡',
			'樂富',
			'鑽石山',
			'慈雲山',
			'牛池灣',

			'kwun tong',
			'觀塘',
			'ping shek',
			'kowloon bay',
			'ngau tau kok',
			'jordan valley',
			'sau mau ping',
			'lam tin',
			'yau tong',
			'lei yue mun',
			'坪石',
			'九龍灣',
			'牛頭角',
			'佐敦谷',
			'秀茂坪',
			'藍田',
			'油塘',
			'鯉魚門',
		],
		self::NEW_TERRITORIES => [
			'new territories',
			'新界',

			'kwai tsing',
			'葵青',
			'kwai chung',
			'tsing yi',
			'葵涌',
			'青衣',

			'tsuen wan',
			'荃灣',
			'lei muk shue',
			'ting kau',
			'sham tseng',
			'tsing lung tau',
			'ma wan',
			'sunny bay',
			'梨木樹',
			'汀九',
			'深井',
			'青龍頭',
			'馬灣',
			'欣澳',

			'tuen mun',
			'屯門',
			'tai lam chung',
			'so kwun wat',
			'lam tei',
			'大欖涌',
			'掃管笏',
			'藍地',

			'yuen long',
			'元朗',
			'hung shui kiu',
			'ha tsuen',
			'lau fau shan',
			'tin shui wai',
			'san tin',
			'lok ma chau',
			'kam tin',
			'shek kong',
			'pat heung',
			'洪水橋',
			'廈村',
			'流浮山',
			'天水圍',
			'新田',
			'落馬洲',
			'錦田',
			'石崗',
			'八鄉',

			'north',
			'北區',
			'fanling',
			'luen wo hui',
			'sheung shui',
			'shek wu hui',
			'sha tau kok',
			'luk keng',
			'wu kau tang',
			'粉嶺',
			'聯和墟',
			'上水',
			'石湖墟',
			'沙頭角',
			'鹿頸',
			'烏蛟騰',

			'tai po',
			'大埔',
			'tai po market',
			'tai po kau',
			'tai mei tuk',
			'shuen wan',
			'cheung muk tau',
			'kei ling ha',
			'大埔墟',
			'大埔滘',
			'大尾篤',
			'船灣',
			'樟木頭',
			'企嶺下',

			'sha tin',
			'沙田',
			'tai wai',
			'fo tan',
			'ma liu shui',
			'wu kai sha',
			'ma on shan',
			'大圍',
			'火炭',
			'馬料水',
			'烏溪沙',
			'馬鞍山',

			'sai kung',
			'西貢',
			'clear water bay',
			'tai mong tsai',
			'tseung kwan o',
			'hang hau',
			'tiu keng leng',
			'ma yau tong',
			'清水灣',
			'大網仔',
			'將軍澳',
			'坑口',
			'調景嶺',
			'馬游塘',

			'islands',
			'離島',
			'cheung chau',
			'peng chau',
			'lantau island (including tung chung)',
			'lamma island',
			'長洲',
			'坪洲',
			'大嶼山(包括東涌)',
			'南丫島',
		],
	];
	// phpcs:enable

	/**
	 * Resolves the WooCommerce Hong Kong state key for a given region, district or sub-district.
	 *
	 * @param string $value  A Hong Kong region, district or sub-district name (English or 中文).
	 * @return string  The WC state key (`HONG KONG`, `KOWLOON` or `NEW TERRITORIES`), or an empty string if not found.
	 */
	public static function get_region_for_district( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		foreach ( self::REGIONS as $region => $districts ) {
			if ( in_array( $value, $districts, true ) ) {
				return $region;
			}
		}

		return '';
	}
}
