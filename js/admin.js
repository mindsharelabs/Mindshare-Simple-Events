const MINDEVENTS_PREPEND = 'mindevents_';

(function( root, $, undefined ) {
	"use strict";

	$(function () {



		function initTimePicker() {
			$('.timepicker').timepicker({
		    timeFormat: 'h:mm p',
		    // interval: 15,
		    minTime: '12:00 AM',
		    maxTime: '11:59 PM',
		    // defaultTime: '7:00 pm',
		    // startTime: '10:00',
		    dynamic: true,
		    dropdown: false,
		    scrollbar: true
			});
		}
		initTimePicker();



		function initDatePicker() {
			if($('.datepicker').length > 0) {
				$( ".datepicker" ).datepicker({
				  'dateFormat': 'yy-m-d'
				});
			}
		}
		initDatePicker();


		$(document).on('change', 'input', function() {
			$(this).removeClass('validate');
		})



		


		$(document).on('click', '.add-offer', function (event) {
			var element = $(this).parent('.single-offer'),
					clone = element.clone( true ).appendTo('#allOffers'),
					add = clone.find('.add-offer')
						.removeClass('add-offer')
						.addClass('remove-offer')
						.html('<span>-</span>');
		});

		$(document).on('click', '.add-offer-edit', function (event) {
			var element = $(this).parent('.single-offer'),
					key = $(this).data('key'),
					clone = element.clone( true ).appendTo('#editOffers_' + key),
					add = clone.find('.add-offer-edit')
						.removeClass('add-offer-edit')
						.addClass('remove-offer')
						.html('<span>-</span>');
		});

		$(document).on('click', '.remove-offer', function(event) {
			$(this).parent('.single-offer').fadeOut(500, function() {
				$(this).remove();
			})
		})



		$(document).on('change', '.field-color', function (e) {
			$(this).css('border-left-color', $(this).val());
			$(this).css('border-left-width', 30);
		})




    $(document).on('click', '.calendar-day', function (event) {

		var emptyInputs = $("#defaultEventMeta").find('input[type="text"].required').filter(function() {
				return $(this).val() == "";
		});

	    if (emptyInputs.length) {
        	emptyInputs.each(function() {
				$(this).addClass('validate');
			});
	    } else {
			event.preventDefault();
			var thisDay = $(this);
			var occurrence = thisDay.siblings('.event');
			var errorBox = $('#errorBox');

			var meta = $('#defaultEventMeta').serializeObject();


			thisDay.addClass('loading').append('<div class="la-ball-fall"><div></div><div></div><div></div></div>');
			var date = $(this).attr('datetime');


			$.ajax({
	  			url : mindeventsSettings.ajax_url,
	  			type : 'post',
	  			data : {
	  				action : MINDEVENTS_PREPEND + 'selectday',
	  				eventid : mindeventsSettings.post_id,
					date : date,
					meta : meta,
					occurrence : occurrence.length
	  			},
	  			success: function(response) {
					thisDay.removeClass('loading');
					thisDay.find('.la-ball-fall').remove();
	          		if(response.html) {
						thisDay.addClass('selected');
						setTimeout(function() {
							thisDay.removeClass('selected');
						}, 400);
						thisDay.attr('event', 'true');
						thisDay.after(response.html);
	          		}
					
					if(response.errors.length > 0) {
						thisDay.addClass('whoops');
						setTimeout(function() {
							thisDay.removeClass('whoops');
						}, 400);

						var items = $("#errorBox > span").length;
						
						$.each(response.errors, function( index, value ) {
							var i = items + 1;
							errorBox.prepend('<span class="error-item-'+ i +'">' + value + '</span>').addClass('show');
							setTimeout(function() {
								$('.error-item-'+ i +'').fadeOut(400, function() {
									$(this).remove();
								});
							}, 3000);
						});
	          		}
	  			},
	  			error: function (response) {
	  				console.log('An error occurred.');
	  				console.log(response);
	  			},
	  		});
		}
  	})



	$(document).on('click', '.calendar-nav .calnav', function (event) {

		event.preventDefault();
		var eventsCalendar = $('#eventsCalendar');
		var calendarTable = $('#mindEventCalendar');
		var month = calendarTable.data('month');
		var year = calendarTable.data('year');
		var direction = $(this).data('dir');


		var height = eventsCalendar.height();
		var width = eventsCalendar.width();
		eventsCalendar.height(height).width(width);
		eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

		$.ajax({
			url : mindeventsSettings.ajax_url,
			type : 'post',
			data : {
				action : MINDEVENTS_PREPEND + 'movecalendar',
				direction : direction,
				month : month,
				year : year,
				eventid : mindeventsSettings.post_id
			},
			success: function(response) {
				eventsCalendar.attr('style', false);
				eventsCalendar.html(response.html);
			},
			error: function (response) {
				console.log('An error occurred.');
				console.log(response);
			},
		});

	})


	$(document).on('click', '.edit-button.update-event', function (event) {
		event.preventDefault();
		var subid = $(this).data('subid');
		var eventsCalendar = $('#eventsCalendar');
		var meta = {};


		var meta = $('#subEventEdit').serializeObject();

		$.ajax({
			url : mindeventsSettings.ajax_url,
			type : 'post',
			data : {
				action : MINDEVENTS_PREPEND + 'updatesubevent',
				eventid : subid,
				parentid : mindeventsSettings.post_id,
				meta : meta
			},
			success: function(response) {
				eventsCalendar.html(response.data.html);
				$('#editBox').fadeOut(200, function() {
					$(this).remove();
				});
			},
			error: function (response) {
				console.log('An error occurred.');
				console.log(response);
			},
		});

	});


	$(document).on('click', '.edit-button.cancel', function (event) {
		event.preventDefault();
		$('#editBox').fadeOut(200, function() {
			$(this).remove();
		});
	});





	$(document).on('click', 'button.atendee-check-in', function (event) {
		event.preventDefault();
		var button = $(this);
		var occurance = $(this).data('occurance');
		var user_id = $(this).data('user_id');
		var akey = $(this).data('akey');

		console.log(occurance);
		console.log(user_id);
		console.log(mindeventsSettings.post_id);
		console.log(button);

		$.ajax({
			url : mindeventsSettings.ajax_url,
			type : 'post',
			data : {
				action : MINDEVENTS_PREPEND + 'checkin_toggle',
				occurance : occurance,
				user_id : user_id,
				akey : akey,
				parentid : mindeventsSettings.post_id,
			},
			beforeSend: function() {
				button.html('Updating...').disabled = true;
			},
			success: function(response) {
				if(response.data.success) {
					if(response.data.new_status == true) {
						button.html('Checked In').addClass('checked-in');
					} else {
						button.html('Check In').removeClass('checked-in');
					}
					button.html(response.data.html);
				}
			},
			error: function (response) {
				console.log('An error occurred.');
				console.log(response);
			},
		});


	});





	$(document).on('click', 'td .event span.edit', function (event) {
		event.preventDefault();
		var thisEvent = $(this).parent('.event');
		var eventid = $(this).data('subid');
		var calendarContainer = $('#eventsCalendar')

		$.ajax({
			url : mindeventsSettings.ajax_url,
			type : 'post',
			data : {
				action : MINDEVENTS_PREPEND + 'editevent',
				eventid : eventid,
					parentid : mindeventsSettings.post_id,
			},
			success: function(response) {
					calendarContainer.prepend('<div id="editBox"></div>');
					$('#editBox').html(response.data.html);
					initTimePicker();
					initDatePicker();
			},
			error: function (response) {
				console.log('An error occurred.');
				console.log(response);
			},
		});


	});


	$(document).on('click', 'td .event span.delete', function (event) {
		event.preventDefault();
		var thisEvent = $(this).parent('.event');
		var eventid = $(this).data('subid');
		var errorBox = $('#errorBox');

		$.ajax({
			url : mindeventsSettings.ajax_url,
			type : 'post',
			data : {
				action : MINDEVENTS_PREPEND + 'deleteevent',
				eventid : eventid,
			},
			success: function(response) {
					thisEvent.fadeOut();
					errorBox.prepend('<span class="error-item-'+ eventid +'">Event deleted</span>').addClass('show');
					setTimeout(function() {
						$('.error-item-'+ eventid +'').fadeOut(400, function() {
							$(this).remove();
						});
					}, 3000);

			},
			error: function (response) {
				console.log('An error occurred.');
				console.log(response);
			},
		});


	});


	$(document).on('click', '.clear-occurances', function (event) {
  		event.preventDefault();

      	if(confirm("Wait a tic! This will remove ALL occurances of this event in every month. You cannot undo this. Are you REALY sure?")) {

			var eventsCalendar = $('#eventsCalendar');
			var height = eventsCalendar.height();
			var width = eventsCalendar.width();
			eventsCalendar.height(height).width(width);
			eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
						action : MINDEVENTS_PREPEND +'clearevents',
					eventid : mindeventsSettings.post_id,
				},
				success: function(response) {
						$('#errorBox').removeClass('show').html('');
						eventsCalendar.html(response.html);
						eventsCalendar.attr('style', false);

				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});

		}
	})


	$.fn.serializeObject = function(){
		var data = {};

		// Manage fields allowing multiple values first (they contain "[]" in their name)
		var final_array = gatherMultipleValues( this );

		// Then, create the object
		$.each(final_array, function() {
			var val = this.value;
			var c = this.name.split('[');
			var a = buildInputObject(c, val);
			$.extend(true, data, a);
		});

		return data;

	};



	function buildInputObject(arr, val) {
		if (arr.length < 1) {
			return val;
		}
		var objkey = arr[0];
		if (objkey.slice(-1) == "]") {
			objkey = objkey.slice(0,-1);
		}
		var result = {};
		if (arr.length == 1){
			result[objkey] = val;
		} else {
			arr.shift();
			var nestedVal = buildInputObject(arr,val);
			result[objkey] = nestedVal;
		}
		return result;
	}

	function gatherMultipleValues( that ) {
		var final_array = [];
		$.each(that.serializeArray(), function( key, field ) {
			// Copy normal fields to final array without changes
			if( field.name.indexOf('[]') < 0 ){
				final_array.push( field );
				return true; // That's it, jump to next iteration
			}

			// Remove "[]" from the field name
			var field_name = field.name.split('[]')[0];

			// Add the field value in its array of values
			var has_value = false;
			$.each( final_array, function( final_key, final_field ){
				if( final_field.name === field_name ) {
					has_value = true;
					final_array[ final_key ][ 'value' ].push( field.value );
				}
			});
			// If it doesn't exist yet, create the field's array of values
			if( ! has_value ) {
				final_array.push( { 'name': field_name, 'value': [ field.value ] } );
			}
		});
		return final_array;
	}



  });

} ( this, jQuery ));
