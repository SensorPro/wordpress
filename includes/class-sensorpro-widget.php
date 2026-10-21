<?php
/**
 * Classic widget.
 *
 * @package SensorPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SensorPro signup widget.
 */
class SensorPro_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'sensorpro_widget',
			__( 'SensorPro Signup Form', 'sensorpro' ),
			array( 'description' => __( 'Signup form that adds contacts to SensorPro.', 'sensorpro' ) )
		);
	}

	/**
	 * Front end.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Settings.
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput -- theme markup.
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$form = SensorPro_Form::render(
			array(
				'title'  => '',
				'tags'   => isset( $instance['tags'] ) ? sanitize_text_field( $instance['tags'] ) : '',
				'button' => isset( $instance['button'] ) ? sanitize_text_field( $instance['button'] ) : '',
			)
		);
		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped inside SensorPro_Form::render().
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Admin form.
	 *
	 * @param array $instance Settings.
	 * @return void
	 */
	public function form( $instance ) {
		$fields = array(
			'title'  => __( 'Title', 'sensorpro' ),
			'tags'   => __( 'Extra tags (comma separated)', 'sensorpro' ),
			'button' => __( 'Button text (optional)', 'sensorpro' ),
		);
		foreach ( $fields as $key => $label ) {
			$val = isset( $instance[ $key ] ) ? $instance[ $key ] : '';
			printf(
				'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s"></p>',
				esc_attr( $this->get_field_id( $key ) ),
				esc_html( $label ),
				esc_attr( $this->get_field_name( $key ) ),
				esc_attr( $val )
			);
		}
	}

	/**
	 * Save.
	 *
	 * @param array $new New values.
	 * @param array $old Old values.
	 * @return array
	 */
	public function update( $new, $old ) {
		return array(
			'title'  => sanitize_text_field( isset( $new['title'] ) ? $new['title'] : '' ),
			'tags'   => implode( ', ', SensorPro_Form::parse_tags( isset( $new['tags'] ) ? $new['tags'] : '' ) ),
			'button' => sanitize_text_field( isset( $new['button'] ) ? $new['button'] : '' ),
		);
	}
}
