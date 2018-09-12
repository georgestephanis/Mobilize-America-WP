(function( $, mA ){

	var eventTemplate = wp.template( 'mobilize-america-event' ),
		$events   = $( '#events-' + mA.slug ),
		$map      = $( '#map-' + mA.slug ),
		lancaster = { lat: 40.039722, lng: -76.304444 },
		map, bounds, renderList,
		locations = {}, markers = {}, infowindows = {};

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
    });

	renderList = function( data ) {
        $events.empty();
        _.each( data, function( event ) {
            $events.append( eventTemplate( event ) );
        } );
	};

	renderList( mA.events.data );

	initMap = function() {
		map = new google.maps.Map( $map[0], {
			center : lancaster,
			zoom : 9,
			disableDefaultUI : true
		});

		bounds = new google.maps.LatLngBounds();

		_.each( mA.byLocation, function( theLocation, theKey ) {
            console.log( theLocation );
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
            });

            google.maps.event.addListener( infowindows[ theKey ], 'closeclick', function(){
                $events.trigger( 'filterByLocation', [ false ] );
            });
        });

		map.fitBounds( bounds );
	};

})( jQuery, mobilizeAmerica );