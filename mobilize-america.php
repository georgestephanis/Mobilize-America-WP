<?php

/**
 * Plugin Name: Mobilize America Integration
 * Plugin Author: George Stephanis
 */


class Mobilize_America {

	/**
	 * Initial kickoff method for class.  Adds the hooks and such.
	 */
	public static function go() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Runs on init.  Sets up the front-end render functions for the dynamic in-page content.
	 */
	public static function init() {
		add_shortcode( 'mobilize_america_events', array( __CLASS__, 'frontend_render_events' ) );
		if ( function_exists( 'register_block_type' ) ) {
			register_block_type( 'mobilize-america/events', array(
				'render_callback' => array( __CLASS__, 'frontend_render_events' ),
			) );
		}
	}

	/**
	 * Sets up the admin ui for Gutenberg and options panels and the like.
	 */
	public static function admin_init() {
		add_settings_section(
			'mobilize-america',
			esc_html__( 'Mobilize America' ),
			array( __CLASS__, 'mobilize_america_settings_section' ),
			'general'
		);

		add_settings_field(
			'mobilize_america_organization_id',
			sprintf( '<label for="mobilize_america_organization_id">%1$s</label>', __( 'Organization ID' ) ),
			array( __CLASS__, 'organization_id_cb' ),
			'general',
			'mobilize-america'
		);
		add_settings_field(
			'googlemaps_api_key',
			sprintf( '<label for="googlemaps_api_key">%1$s</label>', __( 'Google Maps API Key' ) ),
			array( __CLASS__, 'googlemaps_api_key_cb' ),
			'general',
			'mobilize-america'
		);

		register_setting( 'general', 'mobilize_america_options', array( __CLASS__, 'sanitize_options' ) );
	}

	/**
	 * Set up option panel for settings.
	 */
	public static function mobilize_america_settings_section() {
		?>
		<p id="mobilize-america-settings-section">
			<?php _e( 'Settings for Mobilize America integration&hellip;' ); ?>
		</p>
		<?php
	}

	/**
	 * Set up api key option display.
	 */
	public static function organization_id_cb() {
		?>
		<input type="number" class="regular-text code" name="mobilize_america_options[organization_id]" value="<?php echo esc_attr( self::get_option( 'organization_id' ) ); ?>" />
		<?php
	}

	/**
	 * Set up googlemapsapi key option display.
	 */
	public static function googlemaps_api_key_cb() {
		?>
		<input type="text" class="regular-text code" name="mobilize_america_options[googlemaps_key]" value="<?php echo esc_attr( self::get_option( 'googlemaps_key' ) ); ?>" />
		<br /><small><a href="https://developers.google.com/maps/documentation/javascript/get-api-key"><?php esc_html_e( 'Get a Google Maps API Key &rarr;' ); ?></a></small>
		<?php
	}

	/**
	 * Return the requested stored option.
	 *
	 * @param $key
	 * @return null
	 */
	public static function get_option( $key ) {
		$options = get_option( 'mobilize_america_options' );

		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		return null;
	}

	/**
	 * Sanitize and save the options.
	 *
	 * @param $options
	 * @return array
	 */
	public static function sanitize_options( $options ) {
		$options = (array) $options;

		$options['organization_id'] = intval( $options['organization_id'] );
		$options['googlemaps_key']  = $options['googlemaps_key'];

		return $options;
	}

	/**
	 * Shortcode / Block rendering for the events map.
	 *
	 * @param $args
	 * @return string
	 */
	public static function frontend_render_events( $args ) {
		$slug = substr( md5( json_encode( $args ) ), 0, 12 );
		$return = '';

		$events             = self::get_upcoming_events();
		$events_by_location = self::group_events_by_location( $events->data );

		wp_enqueue_script( 'mobilize-america-events', plugins_url('/mobilize-america.js', __FILE__ ), array( 'jquery', 'underscore', 'wp-util' ) );
		wp_localize_script( 'mobilize-america-events', 'mobilizeAmerica', array(
			'slug'       => $slug,
			'events'     => $events,
			'byLocation' => $events_by_location,
		) );

		// If there isn't a Google Maps API key, don't display the map.
		if ( $googlemaps_key = self::get_option( 'googlemaps_key' ) ) {

			$return .= sprintf('<div id="map-%1$s" class="mobilize-america-map"></div>', $slug);
			$return .= '<script>function initMap(){console.log("mobilize-america.js not yet loaded, initMap called too soon.");}</script>';

			wp_enqueue_script('googlemaps', add_query_arg( array(
				'key' => $googlemaps_key,
				'callback' => 'initMap',
			), 'https://maps.googleapis.com/maps/api/js' ) );
		} elseif ( current_user_can( 'manage_options' ) ) {
			// Do a callout to nag the user to get a Google Maps API Key
			$return .= '<h1><a href="' . admin_url( 'options-general.php#mobilize-america-settings-section' ) . '">' . sprintf( 'Hey, %s -- Add a Google Maps API Key to get a map based rendering.', wp_get_current_user()->display_name ) . '</a></h1>';
		}

		wp_enqueue_style('mobilize-america-map', plugins_url('/mobilize-america.css', __FILE__) );

		$return .= '<h3 id="events-list">' . __( 'Upcoming Events:' ) . '</h3>';
		$return .= '<p class="mobilize-america-event-types">' . __( 'Filter by type: ' ) . '<span id="event-' . esc_attr( $slug ) . '-types"></span></p>';
		$return .= "\r\n<ul id='events-{$slug}' class='mobilize-america-events'></ul>\r\n";

		$return .= '<script type="text/html" id="tmpl-mobilize-america-event">
			<li class="vevent ma-event event-{{ data.id }} event-type-{{ data.event_type }}">
				<img src="{{ data.featured_image_url }}" />
				<h2>{{ data.title }}</h2>
				<time class="dtstart" datetime="{{ data._date }}">{{ data.date }}</time>
				
				<section class="summary">
				    <p>{{ data.description }}</p>
				</section>
                <button class="show-more button" onclick=";">' . esc_html__( 'Show More…' ) . '</button>
                
				<# if ( data.location ) { #>
				<address class="location">
					<a href="{{ data.gmaps_link }}" target="_blank"><strong>{{ data.location.venue }}</strong> 🗺️🔗</a> <br />
					{{ data.location.address_lines[0] }}<br />
					{{ data.location.locality }}, {{ data.location.region }} {{ data.location.postal_code }}
				</address>
				<# } #>
				
				<ul class="timeslots qty-timeslots-{{ data.qty_timeslots }}">{{{ data.timeslots_formatted }}}</ul>
				<a href="{{ data.browser_url }}" class="url button button-primary" target="_blank">Sign Up &rarr;</a>
			</li>
		</script>';

		return $return;
	}

	public static function group_events_by_location( $events ) {
		$by_location = array();
		foreach ( $events as $event ) {
		    if ( ! is_object( $event->location ) ) {
		        continue;
            }
			$key = "{$event->location->venue} - {$event->location->location->latitude},{$event->location->location->longitude}";
			$by_location[ $key ]['location'] = $event->location;
			$by_location[ $key ]['events'][] = $event->id;
		}
		return $by_location;
	}

	/**
	 * Cache it in a transient for 15 minutes.
	 *
	 * @return array|mixed|object
	 */
	public static function get_upcoming_events() {
		$url = add_query_arg( array(
			'organization_id' => self::get_option( 'organization_id' ),
			'timeslot_start'  => 'gte_' . ( current_time( 'timestamp' ) - ( 12 * HOUR_IN_SECONDS ) ),
			'per_page'        => 999,
		), 'https://events.mobilizeamerica.io/api/v1/events' );

		// if ( false === ( $events = get_transient( 'mobilize_america_events' ) ) ) {

			$response = wp_remote_get( $url );
			$body     = wp_remote_retrieve_body( $response );
			$events   = json_decode( $body );

			if ( ! $events ) {
			    return (object) array(
                    'events' => array(),
                );
            }

			usort( $events->data, array( __CLASS__, 'sort_events_by_date' ) );
			$events->data = array_map( array( __CLASS__, 'format_event_object' ), $events->data );
			// Reset the timezone back to WP's UTC default.  See https://weston.ruter.net/2013/04/02/do-not-change-the-default-timezone-from-utc-in-wordpress/
			date_default_timezone_set('UTC');

			// set_transient( 'mobilize_america_events', $events, 15 * MINUTE_IN_SECONDS );
		// }

		return $events;
	}

	public static function format_event_object( $event ) {
		if ( $event->timeslots ) {
			date_default_timezone_set( $event->timezone );

			$date_format = get_option( 'date_format' );
			$event->date = date( $date_format, $event->timeslots[0]->start_date );

			$event->qty_timeslots = sizeof( $event->timeslots );
			$end_date = date( $date_format, $event->timeslots[ $event->qty_timeslots - 1 ]->end_date );
			if ( $event->qty_timeslots > 1 && $event->date !== $end_date ) {
				$event->date = sprintf( '%s — %s', $event->date, $end_date );
			}

			$event->_date = date( 'Y-m-d', $event->timeslots[0]->start_date );
			$event->timeslots = array_map( array( __CLASS__, 'format_timeslots' ), $event->timeslots );

			$timeslots_formatted = wp_list_pluck( $event->timeslots, 'formatted' );
			$timeslots_formatted = array_map( 'esc_html', $timeslots_formatted );
			$event->timeslots_formatted = '<li>' . implode( '</li><li>', $timeslots_formatted ) . '</li>';
            if ( is_object( $event->location ) ) {
	            $event->gmaps_link = add_query_arg( array(
		            'api'   => 1,
		            'query' => implode( ' ', array(
				            implode( ' ', $event->location->address_lines ),
				            $event->location->locality . ',',
				            $event->location->region,
				            $event->location->postal_code,
			            )
		            ),
	            ), 'https://www.google.com/maps/search/' );
            }
		}
		return $event;
	}

	public static function format_timeslots( $timeslot ) {
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$start_format = "$date_format @ $time_format";
		$end_format  = $time_format;
		// If it ends on a different day than it starts ...
		if ( date( 'Y-m-d', $timeslot->start_date ) !== date( 'Y-m-d', $timeslot->end_date ) ) {
			$end_format = $start_format;
		}

		$timeslot->formatted = sprintf( '%s — %s',
			date( $start_format, $timeslot->start_date ),
			date( $end_format, $timeslot->end_date ) );

		return $timeslot;
	}

	public static function sort_events_by_date( $event1, $event2 ) {
		return ( $event1->timeslots[0]->start_date < $event2->timeslots[0]->start_date ) ? -1 : 1;
	}
}

Mobilize_America::go();