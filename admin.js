jQuery( document ).ready( function ( $ ) {

	// Open the WP media picker for a slot
	$( document ).on( 'click', '.upload-ad-image', function ( e ) {
		e.preventDefault();

		var $slot = $( this ).closest( '.image-ad-slot' );

		var frame = wp.media( {
			title: 'Select Ad Image',
			button: { text: 'Use this image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$slot.find( '.ad-image-url' ).val( attachment.url );
			$slot.find( '.image-ad-slot__preview' )
				.removeClass( 'image-ad-slot__preview--empty' )
				.html( '<img src="' + attachment.url + '" alt="Ad preview">' );
			$slot.find( '.upload-ad-image' ).text( 'Change Image' );
			$slot.find( '.remove-ad-image' ).show();
		} );

		frame.open();
	} );

	// Remove image from a slot
	$( document ).on( 'click', '.remove-ad-image', function ( e ) {
		e.preventDefault();

		var $slot = $( this ).closest( '.image-ad-slot' );
		$slot.find( '.ad-image-url' ).val( '' );
		$slot.find( '.image-ad-slot__preview' )
			.addClass( 'image-ad-slot__preview--empty' )
			.html( '<span>No image</span>' );
		$slot.find( '.upload-ad-image' ).text( 'Select Image' );
		$( this ).hide();
	} );

} );
