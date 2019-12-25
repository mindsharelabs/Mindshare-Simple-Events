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

		$(document).on('click', '.add-event-occurrence', function (event) {
	    event.preventDefault();
	    var i = $('.time-block').length + 1;
	    $('.time-block').first().clone().find("input").attr('id', function(idx, attrVal) {
	        return attrVal + i;  // change the id
	    }).attr('name', function(idx, attrVal) {
	        return attrVal + i;  // change the name
	    }).val('').removeAttr('checked').end().find('label').attr('for', function(idx, attrVal) {
	        return attrVal + i; // change the for
	    }).end().insertBefore(this);
			initTimePicker();
		});


		$(document).on('click', 'td .event', function (event) {
			console.log('cli');
		});


    $(document).on('click', '.calendar-day', function (event) {
  		event.preventDefault();
			var thisDay = $(this);
			var occurrence = thisDay.siblings('.event');
			var errorBox = $('#errorBox');

			thisDay.addClass('loading').append('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

			var times = { };

			$("#defaultEventTimes .form-section > input").each(function() {
				times[$(this).attr("name")] = $(this).val();
			});
			var date = $(this).attr('datetime');
  		$.ajax({
  			url : mindeventsSettings.ajax_url,
  			type : 'post',
  			data : {
  				action : 'mindevents_selectday',
  				eventid : mindeventsSettings.post_id,
					date : date,
					times : times,
					occurrence : occurrence.length
  			},
  			success: function(response) {
					thisDay.removeClass('loading');
					thisDay.find('.la-ball-fall').remove();
          if(response.html) {
						thisDay.addClass('selected');
						thisDay.attr('event', 'true');
						thisDay.after(response.html);
          }
					if(response.errors.length > 0) {
						$.each(response.errors, function( index, value ) {
						  errorBox.append('<span>' + value + '</span>').addClass('show');
						});
          }
  			},
  			error: function (response) {
  				console.log('An error occurred.');
  				console.log(response);
  			},
  		});

  	})



		$(document).on('click', '.clear-occurances', function (event) {
  		event.preventDefault();


      if(confirm("This will remove ALL occurances of this event. Continue?")) {

					var eventsCalendar = $('#eventsCalendar');
					eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

		  		$.ajax({
		  			url : mindeventsSettings.ajax_url,
		  			type : 'post',
		  			data : {
							action : 'mindevents_clearevents',
		  				eventid : mindeventsSettings.post_id,
		  			},
		  			success: function(response) {
							eventsCalendar.html(response.html);
		          console.log(response);
		  			},
		  			error: function (response) {
		  				console.log('An error occurred.');
		  				console.log(response);
		  			},
		  		});

		  	}
			})



  });

} ( this, jQuery ));
