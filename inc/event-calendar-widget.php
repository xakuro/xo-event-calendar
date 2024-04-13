<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Event calendar widget.
 *
 * @package xo-event-calendar
 */

/**
 * Event calendar widget class.
 */
class XO_Widget_Event_Calendar extends WP_Widget {
	/**
	 * Construction.
	 */
	public function __construct() {
		parent::__construct(
			'xo_event_calendar',
			apply_filters( 'xo_event_calendar_widget_name', __( 'Event Calendar', 'xo-event-calendar' ) ),
			array(
				'classname'   => 'widget_xo_event_calendar',
				'description' => __( 'Display Event Calendar', 'xo-event-calendar' ),
			)
		);
	}

	/**
	 * Echoes the widget content.
	 *
	 * @param array $args     See WP_Widget::widget().
	 * @param array $instance See WP_Widget::widget().
	 */
	public function widget( $args, $instance ) {
		global $xo_event_calendar;

		if ( empty( $instance ) ) {
			$instance = array(
				'title'         => '',
				'cats'          => array(),
				'holidays'      => array(),
				'prev'          => -1,
				'next'          => -1,
				'start_of_week' => 0,
				'months'        => 1,
			);
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$id            = isset( $args['widget_id'] ) ? "{$args['widget_id']}-calendar" : '';
		$categories    = ! empty( $instance['cats'] ) ? implode( ',', $instance['cats'] ) : '';
		$holidays      = ! empty( $instance['holidays'] ) ? implode( ',', $instance['holidays'] ) : '';
		$show_event    = ! empty( $categories );
		$prev          = intval( $instance['prev'] );
		$next          = intval( $instance['next'] );
		$start_of_week = isset( $instance['start_of_week'] ) ? intval( $instance['start_of_week'] ) : 0;
		$months        = isset( $instance['months'] ) ? intval( $instance['months'] ) : 1;
		$allowed_html  = wp_kses_allowed_html( 'post' );

		$allowed_html['button']['onclick'] = true;

		echo wp_kses(
			$xo_event_calendar->get_event_calendar(
				array(
					'id'                      => $id,
					'year'                    => date_i18n( 'Y' ),
					'month'                   => date_i18n( 'n' ),
					'show_event'              => $show_event,
					'categories_string'       => $categories,
					'holidays_string'         => $holidays,
					'prev_month_feed'         => $prev,
					'next_month_feed'         => $next,
					'start_of_week'           => ( -1 === $start_of_week ) ? get_option( 'start_of_week' ) : $start_of_week,
					'months'                  => $months,
					'navigation'              => true,
					'multiple_holiday_classs' => false,
				)
			),
			$allowed_html
		);

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Outputs the settings update form.
	 *
	 * @param array $instance See WP_Widget::form().
	 */
	public function form( $instance ) {
		$title         = isset( $instance['title'] ) ? $instance['title'] : '';
		$cats          = isset( $instance['cats'] ) ? $instance['cats'] : array();
		$holidays      = isset( $instance['holidays'] ) ? $instance['holidays'] : array();
		$prev          = isset( $instance['prev'] ) ? $instance['prev'] : '-1';
		$next          = isset( $instance['next'] ) ? $instance['next'] : '-1';
		$start_of_week = isset( $instance['start_of_week'] ) ? $instance['start_of_week'] : '0';
		$months        = isset( $instance['months'] ) ? $instance['months'] : '1';
		?>
<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#xo_event_holiday ul').sortable({handle: 'span', stop: function(e, ui) {
		$('#xo_event_holiday ul input').change();
	}});
});
</script>
		<?php
		echo '<p>';
		echo '<label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">' . esc_html( __( 'Title:', 'xo-event-calendar' ) ) . '</label>';
		echo '<input class="widefat" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" type="text" value="' . esc_attr( $title ) . '" />';
		echo '</p>' . "\n";

		$terms = get_terms(
			array(
				'taxonomy'   => XO_Event_Calendar::get_taxonomy_type(),
				'hide_empty' => false,
			)
		);
		echo '<span>' . esc_html__( 'Categories:', 'xo-event-calendar' ) . '</span>';
		echo '<div id="' . esc_attr( XO_Event_Calendar::get_taxonomy_type() ) . '" class="xo-event-cat-checklist">';
		echo '<ul>';
		foreach ( $terms as $term ) {
			echo '<li><label class="selectit">';
			echo '<input type="checkbox" value="' . esc_attr( $term->slug ) . '" id="cats-' . esc_attr( $term->slug ) . '" name="' . esc_attr( $this->get_field_name( 'cats[]' ) ) . '" ' . checked( in_array( $term->slug, $cats, true ), true, false ) . '/>' . esc_html( $term->name );
			echo '</label></li>';
		}
		echo '</ul>';
		echo "</div>\n";

		$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );
		if ( ! is_array( $holiday_settings ) ) {
			$holidays = array();
		}
		$full_holidays = $holidays;
		if ( $holiday_settings ) {
			foreach ( $holiday_settings as $key => $value ) {
				if ( array_search( $key, $holidays, true ) === false ) {
					$full_holidays[] = $key;
				}
			}
		}
		echo '<span>' . esc_html__( 'Holiday:', 'xo-event-calendar' ) . '</span>';
		echo '<div id="xo_event_holiday" class="xo-event-cat-checklist">';
		echo '<ul>';
		foreach ( $full_holidays as $holiday ) {
			if ( isset( $holiday_settings[ $holiday ] ) ) {
				$title = $holiday_settings[ $holiday ]['title'];
				echo '<li><span class="dashicons dashicons-menu"></span><label class="selectit"> ';
				echo '<input type="checkbox" value="' . esc_attr( $holiday ) . '" id="holidays-' . esc_attr( $holiday ) . '" name="' . esc_attr( $this->get_field_name( 'holidays[]' ) ) . '" ' . checked( in_array( $holiday, $holidays, true ), true, false ) . '/>' . esc_html( $title );
				echo '</label></li>';
			}
		}
		echo '</ul>';
		echo "</div>\n";

		echo '<p>';
		echo '<span>' . esc_html__( 'Feed month:', 'xo-event-calendar' ) . '</span><br />';
		echo '<label for="' . esc_attr( $this->get_field_id( 'prev' ) ) . '">' . esc_html__( 'Previous month:', 'xo-event-calendar' ) . '</label> ';
		echo '<select id="' . esc_attr( $this->get_field_id( 'prev' ) ) . '" name="' . esc_attr( $this->get_field_name( 'prev' ) ) . '">';
		echo '<option value="-1"' . ( '-1' === $prev ? ' selected="selected"' : '' ) . '>' . esc_html__( 'No limit', 'xo-event-calendar' ) . '</option>';
		echo '<option value="0"' . ( '0' === $prev ? ' selected="selected"' : '' ) . '>0</option>';
		echo '<option value="1"' . ( '1' === $prev ? ' selected="selected"' : '' ) . '>1</option>';
		echo '<option value="2"' . ( '2' === $prev ? ' selected="selected"' : '' ) . '>2</option>';
		echo '<option value="3"' . ( '3' === $prev ? ' selected="selected"' : '' ) . '>3</option>';
		echo '<option value="4"' . ( '4' === $prev ? ' selected="selected"' : '' ) . '>4</option>';
		echo '<option value="5"' . ( '5' === $prev ? ' selected="selected"' : '' ) . '>5</option>';
		echo '<option value="6"' . ( '6' === $prev ? ' selected="selected"' : '' ) . '>6</option>';
		echo '<option value="7"' . ( '7' === $prev ? ' selected="selected"' : '' ) . '>7</option>';
		echo '<option value="8"' . ( '8' === $prev ? ' selected="selected"' : '' ) . '>8</option>';
		echo '<option value="9"' . ( '9' === $prev ? ' selected="selected"' : '' ) . '>9</option>';
		echo '<option value="10"' . ( '10' === $prev ? ' selected="selected"' : '' ) . '>10</option>';
		echo '<option value="11"' . ( '11' === $prev ? ' selected="selected"' : '' ) . '>11</option>';
		echo '<option value="12"' . ( '12' === $prev ? ' selected="selected"' : '' ) . '>12</option>';
		echo '</select> ' . esc_html__( '(month(s))', 'xo-event-calendar' );
		echo '<br />';
		echo '<label for="' . esc_attr( $this->get_field_id( 'next' ) ) . '">' . esc_html__( 'Next month:', 'xo-event-calendar' ) . '</label> ';
		echo '<select id="' . esc_attr( $this->get_field_id( 'next' ) ) . '" name="' . esc_attr( $this->get_field_name( 'next' ) ) . '">';
		echo '<option value="-1"' . ( '-1' === $next ? ' selected="selected"' : '' ) . '>' . esc_html__( 'No limit', 'xo-event-calendar' ) . '</option>';
		echo '<option value="0"' . ( '0' === $next ? ' selected="selected"' : '' ) . '>0</option>';
		echo '<option value="1"' . ( '1' === $next ? ' selected="selected"' : '' ) . '>1</option>';
		echo '<option value="2"' . ( '2' === $next ? ' selected="selected"' : '' ) . '>2</option>';
		echo '<option value="3"' . ( '3' === $next ? ' selected="selected"' : '' ) . '>3</option>';
		echo '<option value="4"' . ( '4' === $next ? ' selected="selected"' : '' ) . '>4</option>';
		echo '<option value="5"' . ( '5' === $next ? ' selected="selected"' : '' ) . '>5</option>';
		echo '<option value="6"' . ( '6' === $next ? ' selected="selected"' : '' ) . '>6</option>';
		echo '<option value="7"' . ( '7' === $next ? ' selected="selected"' : '' ) . '>7</option>';
		echo '<option value="8"' . ( '8' === $next ? ' selected="selected"' : '' ) . '>8</option>';
		echo '<option value="9"' . ( '9' === $next ? ' selected="selected"' : '' ) . '>9</option>';
		echo '<option value="10"' . ( '10' === $next ? ' selected="selected"' : '' ) . '>10</option>';
		echo '<option value="11"' . ( '11' === $next ? ' selected="selected"' : '' ) . '>11</option>';
		echo '<option value="12"' . ( '12' === $next ? ' selected="selected"' : '' ) . '>12</option>';
		echo '</select> ' . esc_html__( '(month(s))', 'xo-event-calendar' );
		echo '</p>' . "\n";

		echo '<p>';
		echo '<label for="' . esc_attr( $this->get_field_id( 'months' ) ) . '">' . esc_html__( 'Months to display:', 'xo-event-calendar' ) . '</label> ';
		echo '<select id="' . esc_attr( $this->get_field_id( 'months' ) ) . '" name="' . esc_attr( $this->get_field_name( 'months' ) ) . '">';
		echo '<option value="1"' . ( '1' === $months ? ' selected="selected"' : '' ) . '>1</option>';
		echo '<option value="2"' . ( '2' === $months ? ' selected="selected"' : '' ) . '>2</option>';
		echo '<option value="3"' . ( '3' === $months ? ' selected="selected"' : '' ) . '>3</option>';
		echo '<option value="4"' . ( '4' === $months ? ' selected="selected"' : '' ) . '>4</option>';
		echo '<option value="5"' . ( '5' === $months ? ' selected="selected"' : '' ) . '>5</option>';
		echo '<option value="6"' . ( '6' === $months ? ' selected="selected"' : '' ) . '>6</option>';
		echo '<option value="7"' . ( '7' === $months ? ' selected="selected"' : '' ) . '>7</option>';
		echo '<option value="8"' . ( '8' === $months ? ' selected="selected"' : '' ) . '>8</option>';
		echo '<option value="9"' . ( '9' === $months ? ' selected="selected"' : '' ) . '>9</option>';
		echo '<option value="10"' . ( '10' === $months ? ' selected="selected"' : '' ) . '>10</option>';
		echo '<option value="11"' . ( '11' === $months ? ' selected="selected"' : '' ) . '>11</option>';
		echo '<option value="12"' . ( '12' === $months ? ' selected="selected"' : '' ) . '>12</option>';
		echo '</select> ' . esc_html__( '(month(s))', 'xo-event-calendar' );
		echo '</p>' . "\n";

		echo '<p>';
		echo '<label for="' . esc_attr( $this->get_field_id( 'start_of_week' ) ) . '">' . esc_html__( 'Week Starts On:', 'xo-event-calendar' ) . '</label> ';
		echo '<select id="' . esc_attr( $this->get_field_id( 'start_of_week' ) ) . '" name="' . esc_attr( $this->get_field_name( 'start_of_week' ) ) . '">';
		echo '<option value="-1"' . ( '-1' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'General Settings', 'xo-event-calendar' ) . '</option>';
		echo '<option value="0"' . ( '0' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Sunday', 'xo-event-calendar' ) . '</option>';
		echo '<option value="1"' . ( '1' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Monday', 'xo-event-calendar' ) . '</option>';
		echo '<option value="2"' . ( '2' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Tuesday', 'xo-event-calendar' ) . '</option>';
		echo '<option value="3"' . ( '3' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Wednesday', 'xo-event-calendar' ) . '</option>';
		echo '<option value="4"' . ( '4' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Thursday', 'xo-event-calendar' ) . '</option>';
		echo '<option value="5"' . ( '5' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Friday', 'xo-event-calendar' ) . '</option>';
		echo '<option value="6"' . ( '6' === $start_of_week ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Saturday', 'xo-event-calendar' ) . '</option>';
		echo '</select>';
		echo '</p>' . "\n";
	}

	/**
	 * Updates a particular instance of a widget.
	 *
	 * @param array $new_instance See WP_Widget::update().
	 * @param array $old_instance See WP_Widget::update().
	 * @return array See WP_Widget::update().
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title']         = wp_strip_all_tags( $new_instance['title'] );
		$instance['cats']          = isset( $new_instance['cats'] ) ? $new_instance['cats'] : array();
		$instance['holidays']      = isset( $new_instance['holidays'] ) ? $new_instance['holidays'] : array();
		$instance['prev']          = isset( $new_instance['prev'] ) ? $new_instance['prev'] : '-1';
		$instance['next']          = isset( $new_instance['next'] ) ? $new_instance['next'] : '-1';
		$instance['start_of_week'] = isset( $new_instance['start_of_week'] ) ? $new_instance['start_of_week'] : '0';
		$instance['months']        = isset( $new_instance['months'] ) ? $new_instance['months'] : '1';

		return $instance;
	}
}
