<?php
/**
 * XO Color.
 *
 * @package xo-event-calendar
 * @since 1.0.0
 */

/**
 * XO Color class.
 */
class XO_Color {
	/**
	 * Get RGB from HEX.
	 *
	 * @since 3.1.0
	 *
	 * @param string $colorcode HEX color code.
	 * @return array RGB color array.
	 */
	public static function get_rgb( $colorcode ) {
		$rgb = array(
			'r' => 0,
			'g' => 0,
			'b' => 0,
		);

		if ( preg_match( '/^#[a-fA-F0-9]{6}$/', $colorcode ) || preg_match( '/^#[a-fA-F0-9]{3}$/', $colorcode ) ) {
			$colorcode = strtr( $colorcode, array( '#' => '' ) );

			$len = ( 6 === strlen( $colorcode ) ) ? 2 : 1;

			$rgb['r'] = hexdec( substr( $colorcode, 0, $len ) );
			$rgb['g'] = hexdec( substr( $colorcode, 1 * $len, $len ) );
			$rgb['b'] = hexdec( substr( $colorcode, 2 * $len, $len ) );
		}

		return $rgb;
	}

	/**
	 * Get RGB from HEX.
	 *
	 * @since 1.0.0
	 * @deprecated 3.1.0 Use get_rgb()
	 *
	 * @param string $colorcode HEX color code.
	 * @return array RGB color array.
	 */
	public static function getRgb( $colorcode ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		return self::get_rgb( $colorcode );
	}

	/**
	 * Get HSV from RGB.
	 *
	 * @since 3.1.0
	 *
	 * @param array $rgb RGB color array.
	 * @return array HSV color array.
	 */
	public static function get_hsv( $rgb ) {
		$hsv = array(
			'h' => 0,
			's' => 0,
			'v' => 0,
		);

		$r = $rgb['r'] / 255;
		$g = $rgb['g'] / 255;
		$b = $rgb['b'] / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );

		$hsv['v'] = $max;

		if ( $max === $min ) {
			$hsv['h'] = 0;
		} elseif ( $r === $max ) {
			$hsv['h'] = 60 * ( ( $g - $b ) / ( $max - $min ) ) + 0;
		} elseif ( $g === $max ) {
			$hsv['h'] = 60 * ( ( $b - $r ) / ( $max - $min ) ) + 120;
		} else {
			$hsv['h'] = 60 * ( ( $r - $g ) / ( $max - $min ) ) + 240;
		}
		if ( $hsv['h'] < 0 ) {
			$hsv['h'] = $hsv['h'] + 360;
		}
		$hsv['s'] = ( 0 !== $hsv['v'] ) ? ( $max - $min ) / $max : 0;

		return $hsv;
	}

	/**
	 * Get HSV from RGB.
	 *
	 * @since 1.0.0
	 * @deprecated 3.1.0 Use get_hsv()
	 *
	 * @param array $rgb RGB color array.
	 * @return array HSV color array.
	 */
	public static function getHsv( $rgb ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		return self::get_hsv( $rgb );
	}

	/**
	 * Get the luma from RGB.
	 *
	 * @param array $rgb RGB color array.
	 * @return float luma (0 to 100).
	 */
	public static function get_luma( $rgb ) {
		// <https://en.wikipedia.org/wiki/Luma_%28video%29>.
		return ( $rgb['r'] * 0.299 + $rgb['g'] * 0.587 + $rgb['b'] * 0.114 ) / 2.55;
	}
}
