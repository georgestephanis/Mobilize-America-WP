(function( $, mA ){

	var eventTemplate = wp.template( 'mobilize-america-event' ),
		$heading      = $( '#events-list' ),
		$events       = $( '#events-' + mA.slug ),
		$map          = $( '#map-' + mA.slug ),
		$eventTypes   = $( '#event-' + mA.slug + '-types' ),
		eventTypes    = _.uniq( _.pluck( mA.events.data, 'event_type' ) ),
		lancaster     = { lat: 40.039722, lng: -76.304444 },
		map, bounds, renderList, showMapForEvents, eventTypes,
		locations = {}, markers = {}, infowindows = {};

	window.addEventListener( 'scroll', function() {
		if ( window.scrollY > $heading.offset().top ) {
			$map.addClass('fixed');
			if ( ( window.scrollY + window.innerHeight ) > ( $events.offset().top + $events.height() ) ) {
				$map.addClass('fixed-bottom');
			} else {
				$map.removeClass('fixed-bottom');
			}
		} else {
			$map.removeClass('fixed');
		}
	});

	// Possibly irrelevant if we're not displaying a description.
	$events.on('click', '.show-more', function(){
		$(this).closest('.ma-event').addClass('expanded');
	});

	$events.on( 'filterByLocation', function( event, theKey ) {
		var event_ids = [],
			all_events = _.clone( mA.events.data );

		if ( theKey ) {
			event_ids = mA.byLocation[ theKey ].events;
			all_events = _.filter( all_events, function( event ) {
				return _.contains( event_ids, event.id );
			})
		}

		renderList( all_events );

		$eventTypes.find('a.current').removeClass('current');
	});


	$events.on( 'filterByType', function( event, type ) {
		var events = _.clone( mA.events.data );

		if ( type ) {
			events = _.filter( events, function( event ) {
				return type === event.event_type;
			});
		}

		renderList( events );

		showMapForEvents( events );
	});

	renderList = function( data ) {
		$events.empty();
		_.each( data, function( event ) {
			$events.append( eventTemplate( event ) );
		} );
	};

	renderList( mA.events.data );

	_.each( eventTypes, function( eventType ) {
		$eventTypes.append( '<a data-type="' + eventType + '" href="javascript:;">' + eventType.replace( '_', ' ' ).toLowerCase() + '</a> ')
	});

	$eventTypes.on('click', 'a[data-type]', function(e){
		var $target = $(e.target);

		if ( $target.hasClass('current') ) {
			$target.removeClass('current');
			$events.trigger( 'filterByType', null );
		} else {
			$target.addClass('current');
			$target.siblings('a').removeClass('current');
			$events.trigger( 'filterByType', [
				$target.data('type')
			]);
		}
		window.scrollTo( 0, $heading.offset().top );
	});

	initMap = function() {
		map = new google.maps.Map( $map[0], {
			center : lancaster,
			zoom : 9,
			disableDefaultUI : true
		});

		bounds = new google.maps.LatLngBounds();

		_.each( mA.byLocation, function( theLocation, theKey ) {
			if ( ! theLocation.location || ! theLocation.location.location.latitude ) {
				return;
			}

			locations[ theKey ] = {
				lat : theLocation.location.location.latitude,
				lng : theLocation.location.location.longitude
			};

			bounds.extend( locations[ theKey ] );

			infowindows[ theKey ] = new google.maps.InfoWindow({
				content : '<div id="content">' + theLocation.location.venue + '<br />' +
							'<a href="#events-list">View ' + theLocation.events.length + " upcoming events 📆</a></div>"
			});

			markers[ theKey ] = new google.maps.Marker({
				position : locations[ theKey ],
				title    : theLocation.location.venue,
				map      : map
			});

			markers[ theKey ].addListener( 'click', function() {
				_.each( infowindows, function( infowindow ) {
					infowindow.close();
				});
				infowindows[ theKey ].open( map, markers[ theKey ] );

				$events.trigger( 'filterByLocation', [ theKey ] );
				window.scrollTo( 0, $heading.offset().top );
			});

			google.maps.event.addListener( infowindows[ theKey ], 'closeclick', function(){
				$events.trigger( 'filterByLocation', [ false ] );
				window.scrollTo( 0, $heading.offset().top );
			});
		});

		map.fitBounds( bounds );
	};

	showMapForEvents = function( events ) {
		var event_ids = _.pluck( events, 'id' );
		_.each( markers, function( marker, key ) {
			// If this location and the events we're showing have any ids in common, show it!
			if ( _.intersection( mA.byLocation[ key ].events, event_ids ).length ) {
				marker.setMap( map );
			} else {
				marker.setMap( null );
			}
		});
	}



})( jQuery, mobilizeAmerica );