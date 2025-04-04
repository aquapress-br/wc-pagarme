( function ( $ ) {

    var form =  $( '#pagarme-recipient-form' ).closest( 'form' );
    var feedback = $( '.pagarme-feedback' );

    $( document ).ready( function () {
		
        // jQuery mask
		$( '#document_cpf' ).mask( '000.000.000-00' );
		$( '#document_cnpj' ).mask( '00.000.000/0000-00' );
		$( '#phone' ).mask( '(00) 0000-00009' );
		$( '#address_zipcode' ).mask( '00000-000' );
		$( '#annual_revenue' ).mask( "#.##0,00", {reverse: true} );
		$( '#monthly_income' ).mask( "#.##0,00", {reverse: true} );
		
		$( '#account_type' ).on( 'change', function () {
			var accountType = $( this ).val();
			if ( 'individual' === accountType ) {
				$( '#corporation_fields' ).addClass( 'hidden' );
			} else if ( 'corporation' === accountType ) {				
				$( '#corporation_fields' ).removeClass( 'hidden' );
			}

		}).change();

		$( '#transfer_interval' ).on( 'change', function () {
			var transferInterval = $( this ).val();
			$( '.weekly_transfer_day, .monthly_transfer_day' ).addClass( 'hidden' );
			if ( 'weekly' === transferInterval ) {				
				$( '.weekly_transfer_day' ).removeClass( 'hidden' );
			} else if ( 'monthly' === transferInterval ) {				
				$( '.monthly_transfer_day' ).removeClass( 'hidden' );
			}
		}).change();
        
        // Submit recipient data
       $( form ).off( 'submit' ).on( 'submit', function ( e ) {
            e.preventDefault();
			
            var self = $( this ).clone(true),
				accountType = $( '#account_type' ).val();

			$( this ).find( 'select' ).each( function( index ) {
				var selectedValue = $( this ).val();
				self.find('select').eq( index ).val( selectedValue );
			} );
			
			// Remove unused fields before the request
			if ( 'individual' === accountType ) {
				self.find( '#corporation_fields' ).remove();
			}
			
			// Builder request data
            var data = {
                action: 'update_recipient_data',
				nonce: PAGARME_MKTPC.nonce,
                data: self.serialize(),
            };

            feedback.fadeOut();

            $.post( PAGARME_MKTPC.ajaxurl, data, function ( resp ) {
                if ( resp.success == true ) {
                    feedback.removeClass( 'dokan-alert-danger' );
                    feedback.addClass( 'dokan-alert dokan-alert-success' );
                    feedback.html( resp.data );
                    feedback.fadeIn();                 
                } else {
                    feedback.addClass( 'dokan-alert dokan-alert-danger' );
                    feedback.html( resp.data );
                    feedback.fadeIn();
                }
				$( 'html, body' ).animate({ scrollTop: $( '.dokan-dashboard-header' ).offset().top }, 'slow' );
            } );
        } );
    } );

} )( jQuery );