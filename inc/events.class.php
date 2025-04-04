<?php

 
 class mindEventCalendar {
 
 private $eventID;
 private $wp_post;

 private $displayType;
 private $calendar_start_day;
 private $currency_symbol;
 private $options;
 private $show_past_events;
 private $next_month;

 private $weekDayNames;
 private $now;
 private $today;
 private $all_events;

 private $event_categories = false;

 private $is_archive = false;


 private $classes = [
   'calendar'     => 'mindEventCalendar',
   'leading_day'  => 'SCprefix',
   'trailing_day' => 'SCsuffix',
   'today'        => 'today',
   'event'        => 'event',
   'events'       => 'events',
   'past'         => 'past-date',
 ];

 private $dailyHtml = [];
 private $offset = 0;

 function __construct($id = '', $calendarDate = null, $today = null ) {


   $this->setToday($today);
   $this->setCalendarClasses();


   if($id == 'archive') :
     $this->all_events = $this->get_all_events();
   endif;

   if($calendarDate) :
     $this->setDate($calendarDate);
   elseif(get_post($id)) :
     $this->setDate(get_post_meta($id, 'first_event_date', true));
   endif;

   $this->options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
   $this->eventID = $id;
   $this->wp_post = get_post($id);

   $this->show_past_events = true;


   $date = new DateTime('now');
   $date->modify('first day of next month');
   $this->next_month = $date->format('m');

   $this->currency_symbol = (isset($this->options[MINDEVENTS_PREPEND . 'currency_symbol']) ? $this->options[MINDEVENTS_PREPEND . 'currency_symbol'] : '$');
   $this->calendar_start_day = (isset($this->options[MINDEVENTS_PREPEND . 'start_day']) ? $this->options[MINDEVENTS_PREPEND . 'start_day'] : 'Monday');


 }
 private function define( $name, $value ) {
   if ( ! defined( $name ) ) {
     define( $name, $value );
   }
 }

 /**
  * Sets the date for the calendar.
  *
  * @param \DateTimeInterface|int|string|null $date DateTimeInterface or Date string parsed by strtotime for the
  *     calendar date. If null set to current timestamp.
  */
 public function setDate( $date = null ) {
   $this->now = $this->parseDate($date) ?: new \DateTimeImmutable();
 }


 /**
  * @param \DateTimeInterface|int|string|null $date
  * @return \DateTimeInterface|null
  */
 private function parseDate( $date = null ) {
   if( $date instanceof \DateTimeInterface ) {
     return $date;
   }
   if( is_int($date) ) {
     return (new \DateTimeImmutable())->setTimestamp($date);
   }
   if( is_string($date) ) {
     return new \DateTimeImmutable($date);
   }

   return null;
 }

 /**
  * Sets the class names used in the calendar
  *
  * ```php
  * [
  *    'calendar'     => 'mindEventsCalendar',
  *    'leading_day'  => 'SCprefix',
  *    'trailing_day' => 'SCsuffix',
  *    'today'        => 'today',
  *    'event'        => 'event',
  *    'events'       => 'events',
  * ]
  * ```
  *
  * @param array $classes Map of element to class names used by the calendar.
  */
 public function setCalendarClasses( array $classes = [] ) {
   foreach( $classes as $key => $value ) {
     if( !isset($this->classes[$key]) ) {
       throw new \InvalidArgumentException("class '{$key}' not supported");
     }

     $this->classes[$key] = $value;
   }
 }



 public function setEventCategories($array = array()) {
   $this->event_categories = $array;
 }

 /**
  * Sets "today"'s date. Defaults to today.
  *
  * @param \DateTimeInterface|false|string|null $today `null` will default to today, `false` will disable the
  *     rendering of Today.
  */
 public function setToday( $today = null ) {
   if( $today === false ) {
     $this->today = null;
   } elseif( $today === null ) {
     $this->today = new \DateTimeImmutable();
   } else {
     $this->today = $this->parseDate($today);
   }
 }

 /**
  * @param string[]|null $weekDayNames
  */
 public function setWeekDayNames( array $weekDayNames = null ) {
   if( is_array($weekDayNames) && count($weekDayNames) !== 7 ) {
     throw new \InvalidArgumentException('week array must have exactly 7 values');
   }

   $this->weekDayNames = $weekDayNames ? array_values($weekDayNames) : null;
 }

 /**
  * Add a daily event to the calendar
  *
  * @param string                             $html The raw HTML to place on the calendar for this event
  * @param \DateTimeInterface|int|string      $startDate Date string for when the event starts
  * @param \DateTimeInterface|int|string|null $endDate Date string for when the event ends. Defaults to start date
  */
 public function addDailyHtml( $html, $startDate, $endDate = null ) {
   static $htmlCount = 0;

   $start = $this->parseDate($startDate);
   if( !$start ) {
     throw new \InvalidArgumentException('invalid start time');
   }

   $end = $start;
   if( $endDate ) {
     $end = $this->parseDate($endDate);
   }
   if( !$end ) {
     throw new \InvalidArgumentException('invalid end time');
   }

   if( $end->getTimestamp() < $start->getTimestamp() ) {
     throw new \InvalidArgumentException('end must come after start');
   }

   $working = (new \DateTimeImmutable())->setTimestamp($start->getTimestamp());
   do {
     $tDate = getdate($working->getTimestamp());

     $this->dailyHtml[$tDate['year']][$tDate['mon']][$tDate['mday']][$htmlCount] = $html;

     $working = $working->add(new \DateInterval('P1D'));
   } while( $working->getTimestamp() < $end->getTimestamp() + 1 );

   $htmlCount++;
 }

 /**
  * Clear all daily events for the calendar
  */
 public function clearDailyHtml() { $this->dailyHtml = []; }


 /**
  * Sets the first day of the week
  *
  * @param int|string $offset Day the week starts on. ex: "Monday" or 0-6 where 0 is Sunday
  */
 public function setStartOfWeek( $offset ) {
   if( is_int($offset) ) {
     $this->offset = $offset % 7;
   } elseif( $this->weekDayNames !== null && ($weekOffset = array_search($offset, $this->weekDayNames, true)) !== false ) {
     $this->offset = $weekOffset;
   } else {
     $weekTime = strtotime($offset);
     if( $weekTime === 0 ) {
       throw new \InvalidArgumentException('invalid offset');
     }

     $this->offset = date('N', $weekTime) % 7;
   }
 }



 /**
  * Returns the generated Calendar
  *
  * @return string
  */
 public function render() {
   $out = '';
   $now   = getdate($this->now->getTimestamp());
   $today = [ 'mday' => -1, 'mon' => -1, 'year' => -1 ];
   if( $this->today !== null ) {
     $today = getdate($this->today->getTimestamp());
   }

   $daysOfWeek = $this->weekdays();
   $this->rotate($daysOfWeek, $this->offset);

   $weekDayIndex = date('N', mktime(0, 0, 1, $now['mon'], 1, $now['year'])) - $this->offset;
   $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $now['mon'], $now['year']);


     $out .= '<h4 class="month-display">' . $now['month'] . ' ' . $now['year'] . '</h4>';
     $out .= '<table id="mindEventCalendar" data-month="' . $now['mon'] . '" data-year="' . $now['year'] . '" cellpadding="0" cellspacing="0" class=" ' . $this->classes['calendar'] . '"><thead><tr>';
       foreach( $daysOfWeek as $dayName ) {
         $out .= '<th>' . $dayName . '</th>';
       }
     $out .= '</tr></thead><tbody><tr>';

     $weekDayIndex = ($weekDayIndex + 7) % 7;
     if( $weekDayIndex === 7 ) {
       $weekDayIndex = 0;
     } else {
       $out .= str_repeat('<td class="' . $this->classes['leading_day'] . '">&nbsp;</td>', $weekDayIndex);
     }

     $count = $weekDayIndex + 1;
     for( $i = 1; $i <= $daysInMonth; $i++ ) {
       $date = (new \DateTimeImmutable())->setDate($now['year'], $now['mon'], $i);

       $isToday = false;
       $isPast = false;
       if( $this->today !== null ) {
         $isToday = $i == $today['mday']
           && $today['mon'] == $date->format('n')
           && $today['year'] == $date->format('Y');

         $isPast = $this->today->format('Y-m-d') > $date->format('Y-m-d');
       }

       $out .= '<td class="' . ($isToday ? $this->classes['today'] : '') . ' ' . ($isPast ? $this->classes['past'] : '') . '">';

       $out .= sprintf('<time class="calendar-day" datetime="%s">%d</time>', $date->format('Y-m-d'), $i);

       $dailyHTML = null;
       if( isset($this->dailyHtml[$now['year']][$now['mon']][$i]) ) {
         $dailyHTML = $this->dailyHtml[$now['year']][$now['mon']][$i];
       }

       if( is_array($dailyHTML) ) {
         $out .= '<div class="events">';
         foreach( $dailyHTML as $dHtml ) {
           $out .= $dHtml;
         }
         $out .= '</div>';
       }

       $out .= '</td>';

       if( $count > 6 ) {
         $out   .= '</tr><tr class="meta-container"><td class="eventMeta" colspan="7"></td></tr>' . ($i < $daysInMonth ? '<tr>' : '');
         $count = 0;
       }
       $count++;
     }

     if( $count !== 1 ) {
       $out .= str_repeat('<td class="' . $this->classes['trailing_day'] . '">&nbsp;</td>', 8 - $count) . '</tr>';
     }

     $out .= '<tr class="meta-container"><td class="eventMeta" colspan="7"></tbody></table>';

   return $out;
 }



 /**
  * @param int $steps
  */
 private function rotate( array &$data, $steps ) {
   $count = count($data);
   if( $steps < 0 ) {
     $steps = $count + $steps;
   }
   $steps %= $count;
   for( $i = 0; $i < $steps; $i++ ) {
     $data[] = array_shift($data);
   }
 }

 /**
  * @return string[]
  */
 private function weekdays() {
   if( $this->weekDayNames !== null ) {
     $wDays = $this->weekDayNames;
   } else {
     $today = (86400 * (date('N')));
     $wDays = [];
     for( $n = 0; $n < 7; $n++ ) {
       $wDays[] = date('D', time() - $today + ($n * 86400));
     }
   }

   return $wDays;
 }


 public function set_past_events_display($display) {
   if(is_string($display)) :
     if($display === '1') {$display = true;}
     if($display === '0') {$display = false;}
   endif;

   if($display === false) {
     $this->show_past_events = false;
   } else {
     $this->show_past_events = true;
   }
 }


 public function get_all_events($args = array()) {

   $defaults = array(
     'meta_query' => array(
       // 'relation' => 'AND',
       'start_clause' => array(
         'key' => 'starttime',
         'compare' => 'EXISTS',
       ),
       'date_clause' => array(
         'key' => 'event_date',
         'compare' => 'EXISTS',
       ),
     ),
     'orderby' => 'meta_value',
     'meta_key' => 'event_time_stamp',
     'meta_type' => 'DATETIME',

     'order'            => 'ASC',
     'post_type'        => 'sub_event',
     'suppress_filters' => true,
     'posts_per_page'   => -1
   );
   if($this->show_past_events == false) {
     $args['meta_query'][] = array(
       'key' => 'event_time_stamp', // Check the start date field
       'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
       'compare' => '>=', // Return the ones greater than today's date
       'type' => 'DATETIME' // Let WordPress know we're working with date
     );
   }

   if($this->event_categories) {
     $args['tax_query'] = array(
       array(
         'taxonomy' => 'event_category',
         'field'    => 'slug',
         'terms'    => $this->event_categories,
       ),
     );
   }

   $args = wp_parse_args($args, $defaults);
   return get_posts($args);

 }

 public function get_sub_events($args = array()) {
  
   $defaults = array(
     'meta_query' => array(
       'relation' => 'AND',
       'start_clause' => array(
         'key' => 'starttime',
         'compare' => 'EXISTS',
       ),
       'date_clause' => array(
         'key' => 'event_date',
         'compare' => 'EXISTS',
       ),
     ),
     'orderby' => 'meta_value',
     'meta_key' => 'event_time_stamp',
     'meta_type' => 'DATETIME',
     'order'            => 'ASC',
     'post_type'        => 'sub_event',
     'post_parent'      => $this->eventID,
     'suppress_filters' => true,
     'posts_per_page'   => -1
   );

   if($this->show_past_events === false) {
     $args['meta_query'][] = array(
       'key' => 'event_time_stamp', // Check the start date field
       'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
       'compare' => '>=', // Return the ones greater than today's date
       'type' => 'DATETIME' // Let WordPress know we're working with date
     );
   }
   if($this->event_categories) {
     $args['tax_query'] = array(
       array(
         'taxonomy' => 'event_category',
         'field'    => 'slug',
         'terms'    => $this->event_categories,
       ),
     );
   }

   if(is_admin()) :
    unset($defaults['post_parent']);
   endif;

   $args = wp_parse_args($args, $defaults);

   return get_posts($args);

 }



 public function get_front_calendar() {

   $this->setStartOfWeek($this->calendar_start_day);
   $eventDates = $this->get_sub_events();


   if($eventDates) :
     foreach ($eventDates as $key => $event) :
       $is_past = $this->today->format('Y-m-d') > get_post_meta($event->ID, 'event_date', true) ? true : false;
       $date = get_post_meta($event->ID, 'event_date', true);
       $color = $this->get_event_color($event->ID);

       $text_color = $this->getContrastColor($color);

       $insideHTML = '<div class="event ' . (MINDEVENTS_IS_MOBILE ? 'mobile' : '') . ' ' . ($is_past ? 'past-event' : '') . '">';
         $insideHTML .= '<div class="sub-event-toggle" data-eventid="' . $event->ID . '" style="color:' . $text_color . '; background:' . $color .'" >';
           $insideHTML .= $this->get_event_label($event);
         $insideHTML .= '</div>';
       $insideHTML .= '</div>';


       $eventDates = $this->addDailyHtml($insideHTML, $date);
     endforeach;
   endif;

   return $this->render();;
 }


 private function get_event_label($event) {

   $html = '';
   $starttime = get_post_meta($event->ID, 'starttime', true);
   $endtime = get_post_meta($event->ID, 'endtime', true);
   $thumb = get_the_post_thumbnail($event->post_parent, 'cal-thumb');
   $html .= '<div class="event-label-container">';
     //TODO: uswe badge image in place of thumnmail
       // if($thumb) :
       //     $html .= '<div class="event-thumb">' . $thumb . '</div>';
       // endif;
       $html .= '<div class="event-meta">';
         $html .= '<span class="event-title">' . get_the_title($event->post_parent) . '</span>';
         $html .= '<span class="event-time">' . $starttime . ' - ' . $endtime . '</span>';
       $html .= '</div>';
   $html .= '</div>';
   return $html;
 }


 public function get_front_list($calDate = '') {
   if($calDate == 'archive') :
     $this->is_archive = true;
     $this->show_past_events = false;
   endif;
   $eventDates = $this->get_sub_events();
   $event_type = get_post_meta(get_the_id(), 'event_type', true);

   $i = 0;

   if(count($eventDates) > 0) :

     foreach ($eventDates as $key => $event) :
       $display_link = ($event_type == 'single-event' && $i < 1) ? false : true;
       $startDate = get_post_meta($event->ID, 'event_date', true);
       $this->addDailyHtml($this->get_list_item_html($event->ID, $display_link), $startDate);
       $i++;
     endforeach;
     $html = $this->renderList();
   else :
     $html = '<p class="no-events">There are no ' . ($this->show_past_events  ? 'events' : 'upcoming events.');
   endif;

   return $html;
 }





 /**
  * Returns a list of sub events
  *
  * @return string
  */
 public function renderList() {
   $now = getdate($this->now->getTimestamp());
   $out = '<div id="mindCalanderList" class="event-list ' . $this->classes['calendar'] . '">';
     if(is_array($this->dailyHtml)) :
       $number_of_years = count($this->dailyHtml);
       foreach ($this->dailyHtml as $year => $year_items) :
         foreach ($year_items as $month => $month_items) :
           foreach ($month_items as $day => $daily_items) :
             $date = (new DateTime())->setDate($year, $month, $day);
             $date_format = ($number_of_years > 1) ? 'l, M j Y' : 'l, M j';
               $out .= '<div class="list_day_container">';
                 $out .= '<div class="day-label"><time class="calendar-day" datetime="' . $date->format('Y-m-d') .'">' . $date->format($date_format) . '</time></div>';
                 foreach ($daily_items as $key => $dHTML) :
                   $out .= $dHTML;
                 endforeach;
               $out .= '</div>';

           endforeach;
         endforeach;
       endforeach;
     endif;

   $out .= '</div>';

   return $out;
 }


 public function get_list_item_html($event = '', $display_link = true) {





   $meta = get_post_meta($event);
   $image = apply_filters(MINDEVENTS_PREPEND . 'event_image', get_the_post_thumbnail( get_post_parent( $event ), 'medium', array('class' => 'event-image') ), $event);
   $is_past = $this->today->format('Y-m-d') > $meta['event_date'][0] ? true : false;
   $parentID = wp_get_post_parent_id($event);
   $sub_event_obj = get_post($event);

   $parent_event_type = get_post_meta($parentID, 'event_type', true);
   if($parent_event_type == 'single-event') :
     $series_start_date = get_post_meta($parentID, 'first_event_date', true);
     $series_end_date = get_post_meta($parentID, 'last_event_date', true);
     $series_started = $this->today->format('Y-m-d') > $series_start_date ? true : false;
     $series_ended = $this->today->format('Y-m-d') > $series_end_date ? true : false;
   endif;



   if($meta) :
     $style_str = array();

     $color = $this->get_event_color($event);


     $description = ($meta['eventDescription'][0] ? $meta['eventDescription'][0] : get_the_excerpt( get_post_parent($event) ));


     if($color) :
       $style_str['border-color'] = 'border-color:' . $color . ';';
       $style_str['color'] = 'color:' . $color . ';';
     endif;

     $html = '<div class="item_meta_container">';
       if($is_past) :
         $html .= '<div class="past-event event-notice">This event has passed.</div>';
       endif;

       if($parent_event_type == 'single-event') :
         if($series_started && !$series_ended) :
             $html .= '<div class="series-started event-notice"><strong>This multiday event has started.</strong></div>';
         endif;
         if($series_ended) :
             $html .= '<div class="series-ended event-notice"><strong>This series has ended.</strong></div>';
         endif;
       endif;
       if($this->is_archive) :
         if($sub_event_obj->post_parent) :
           $html .= '<div class="meta-item">';
             $html .= '<a style="' . implode(' ', $style_str) .'" href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
               $html .= '<h3 class="event-title">' . get_the_title($sub_event_obj->post_parent) . '</h3>';
             $html .= '</a>';
           $html .= '</div>';
         endif;


         if($image) :
           $html .= '<div class="featured-image">';
             $html .= '<a href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
               $html .= $image;
             $html .= '</a>';
           $html .= '</div>';
         endif;
       endif;

       if($meta['event_date'][0]) :
         $html .= '<div class="meta-item time-span">';
           $html .= '<div class="starttime">';
             $html .= '<span class="label">' . apply_filters(MINDEVENTS_PREPEND . 'start_time_label', 'Start Time') . '</span>';
             $html .= '<span class="value eventstarttime">' . $meta['starttime'][0] . '</span>';
           $html .= "</div>";

           $html .= '<div class="endtime">';  
             $html .= '<span class="label">' . apply_filters(MINDEVENTS_PREPEND . 'end_time_label', 'End Time') . '</span>';
             $html .= '<span class="value eventendtime">' . $meta['endtime'][0] . '</span>';
           $html .= '</div>';
         $html .= '</div>';
       endif;



       if($description) :
         $html .= '<div class="meta-item description">';
           $html .= '<span class="value eventdescription">' . $description . '</span>';
         $html .= '</div>';
       endif;



       if($display_link) : //hide individual links because if this is a series
         if($meta['linked_product'][0]) :

           $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
           $product = wc_get_product( $meta['linked_product'][0] );


           if($product) :

               $html .= $this->build_offer_link(array(
                   'label' => $meta['wooLabel'][0],
                   'price' => $product->get_price(),
                   'link' => $product->get_permalink(), 
                   'background' => $color,
                   'color' => $this->getContrastColor($color),
                   'product_id' => $meta['linked_product'][0],
                   'event_date' => $event_start_date->format('D, M d Y @ H:i'),
                   'quantity' => 1
                 ));

           endif;

         elseif($meta['offers'][0]) :
           $offers = unserialize($meta['offers'][0]);
           $html .= '<div class="offers meta-item">';
           foreach ($offers as $key => $offer) :
             $html .= $this->build_offer_link($offer);
           endforeach;
           $html .= '</div>';
         endif;
       endif;

     $html .= '</div>';
   endif;
   return $html;

 }

 public function build_offer_link($offer) {
   if(!$offer['label']) :
     $offer['label'] = __('Add to Cart', 'makesantafe');
   endif;

   $style_str = 'color: ' . $offer['color'] . '; border-color:' . $offer['color'] . '; background: ' . $offer['background'] . ';';
   $options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
   $html = '<div class="meta-item link">';
       $html .= '<div class="offer-link">';
         // $html .= '<span class="label">' . apply_filters(MINDEVENTS_PREPEND . 'cost_label', $offer['label']) . '</span>';


         if($options[MINDEVENTS_PREPEND . 'enable_woocommerce']) :

           //check if product is in stock
           $product = wc_get_product( $offer['product_id'] );
           $in_cart = in_array( $offer['product_id'], array_column( WC()->cart->get_cart(), 'product_id' ) );
           if(!$product->is_in_stock()) :
             $stock = false;
           else :
             $stock = $product->get_stock_quantity();
           endif;

           $html .= '<button 
             data-product_id="' . $offer['product_id'] . '"
             data-quantity="' . $offer['quantity'] . '"
             data-event_date="' . $offer['event_date'] . '"
             class="button mindevents-add-to-cart" 
             style="' . $style_str . '"
             ' . ($stock ? '' : 'disabled') . '
             >';


             if($in_cart) :
               $html .= '<span class="in-cart">Item In Cart, Add more (+1)</span>';
             else :
               if($stock) :
                 $html .= $offer['label'] . ' - ' . ($offer['price'] ? $this->currency_symbol . $offer['price'] : '');
                 if($stock > 0) :
                   $html .= ' (' . $stock . ' Available)';
                 endif;
               else :
                 $html .= 'Out of Stock';
               endif;
             endif;

           $html .= '</button>';

           if($in_cart ) :
             $html .= '<a href="' . wc_get_cart_url() . '" style="' . $style_str . '" class="button go-to-cart">Go to cart.</a>';
           endif;
         else :

           $html .= '<a href="' . $offer['link'] . '" class="button" target="_blank" style="' . $style_str . '">';

             $html .= ($offer['price'] ? $this->currency_symbol . $offer['price'] : $offer['label']);

           $html .= '</a>';

         endif;




         $html .= '</div>';
       $html .= '</div>';
   return $html;
 }





 private function get_variation_sku_from_date($eventID, $start_date) {
   return sanitize_title($eventID . '_' . $start_date);
 }


 private function get_event_color($eventID) {
   $meta = get_post_meta($eventID);
   $color = (isset($meta['eventColor'][0]) ? $meta['eventColor'][0] : false);
   if(!$color) :
     //get event category
     $event_categories = get_the_terms($eventID, 'event_category');
     if(!$event_categories) :
       $event_categories = get_the_terms(get_post($eventID)->post_parent, 'event_category');
     endif;



     if($event_categories) :
       foreach ($event_categories as $key => $term) :
         $color = get_field('event_color', $term->taxonomy . '_' . $term->term_id);
         if($color) :
           break;
         endif;
       endforeach;
     endif;


     if(!$color) :
       $color = '#858585';
     endif;
   endif;
   return $color;

 }


 public function get_cal_meta_html($event = '') {
   $meta = get_post_meta($event);
   $parentID = wp_get_post_parent_id($event);
   $sub_event_obj = get_post($event);

   //add a filter for event image
   $image = apply_filters(MINDEVENTS_PREPEND . 'event_image', get_the_post_thumbnail( get_post_parent( $event ), 'medium', array('class' => 'event-image') ), $event);
   $is_past = $this->today->format('Y-m-d') > $meta['event_date'][0] ? true : false;



   $parent_event_type = get_post_meta($parentID, 'event_type', true);
   if($parent_event_type == 'single-event') :
     $series_start_date = get_post_meta($parentID, 'first_event_date', true);
     $series_end_date = get_post_meta($parentID, 'last_event_date', true);
     $series_started = $this->today->format('Y-m-d') > $series_start_date ? true : false;
     $series_ended = $this->today->format('Y-m-d') > $series_end_date ? true : false;
   endif;

   if($meta) :
     $style_str = array();
     $color = $this->get_event_color($event);
     $description = ($meta['eventDescription'][0] ? $meta['eventDescription'][0] : get_the_excerpt( get_post_parent($event) ));


     if($color) :
       $style_str['background'] = 'background:' . $color . ';';
       $style_str['color'] = 'color:' . $this->getContrastColor($color) . ';';
     endif;

     $html = '<div class="meta_inner_container ' . ($is_past ? 'past-event' : '') . '" style="' . implode(' ', $style_str) . '">';

       $html .= '<button class="event-meta-close"><i class="fas fa-times"></i></button>';


       if($image) :
         $html .= '<div class="featured-image">';
           $html .= '<a href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
             $html .= $image;
           $html .= '</a>';
         $html .= '</div>';
       endif;

       $html .= '<div class="left-content">';
         if($is_past) :
           $html .= '<div class="past-event event-notice">This event has passed.</div>';
         endif;

         if($parent_event_type == 'single-event') :
           if($series_started && !$series_ended) :
               $html .= '<div class="series-started event-notice"><strong>This multiday event has started.</strong></div>';
           endif;
           if($series_ended) :
               $html .= '<div class="series-ended event-notice"><strong>This event has passed.</strong></div>';
           endif;
         endif;


         if($sub_event_obj->post_parent) :
           $html .= '<div class="meta-item">';
             $html .= '<a style="' . implode(' ', $style_str) .'" href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
               $html .= '<h3 class="event-title">' . get_the_title($sub_event_obj->post_parent) . '</h3>';
             $html .= '</a>';
           $html .= '</div>';
         endif;

         if($meta['event_date'][0]) :
           $date = new DateTime($meta['event_date'][0]);
           $html .= '<div class="meta-item">';
             $html .= '<span class="value eventdate"><strong>' . $date->format('F j, Y') . ' @ ' . $meta['starttime'][0] . ($meta['endtime'][0] ? ' - ' . $meta['endtime'][0] : '') . '</strong></span>';
           $html .= '</div>';
         endif;


         if(isset($meta['eventCost'][0])) :
           $html .= '<div class="meta-item">';
             $html .= '<span class="value eventcost">' . $meta['eventCost'][0] . '</span>';
           $html .= '</div>';
         endif;

         if($description) :
           $html .= '<div class="meta-item">';
             $html .= '<span class="value eventdescription">' . $description . '</span></br>';
             $html .= '<a href="' . get_permalink($sub_event_obj->post_parent) . '" style="' . $style_str['color'] . '" class="event-info-link"> Read More</span></a>';
           $html .= '</div>';
         endif;

         $style_str['border-color'] = 'border-color:' . $this->getContrastColor($color) . ';';




       $html .= '</div>';

       $html .= '<div class="right-content">';
         

         

         $offers = unserialize ($meta['offers'][0]);

         $has_tickets = $has_tickets = get_post_meta($sub_event_obj->post_parent, 'has_tickets', true);
         if($has_tickets) :
          if($meta['linked_product'][0]) :

              $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
              $product = wc_get_product( $meta['linked_product'][0] );

              if($product) :

                  $html .= $this->build_offer_link(array(
                      'label' => $meta['wooLabel'][0],
                      'price' => $product->get_price(),
                      'link' => $product->get_permalink(), 
                      'background' => $color,
                      'color' => $this->getContrastColor($color),
                      'product_id' => $meta['linked_product'][0],
                      'event_date' => $event_start_date->format('D, M d Y @ H:i'),
                      'quantity' => 1
                    ));

              endif;

          elseif(count($offers) > 0) :

              foreach ($offers as $key => $offer) :
                  $offer['background'] = $color;
                  $offer['color'] = $this->getContrastColor($color);
                  $html .= $this->build_offer_link($offer);
                endforeach;


          else :
            
            //get first sub event
            $sub_events = $this->get_sub_events(array('posts_per_page' => 1));
            if($sub_events) :
              $sub_event = $sub_events[0];
              $meta = get_post_meta($sub_event->ID);
              $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
              $product = wc_get_product( $meta['linked_product'][0] );
              if($product) :
              
                  $html .= $this->build_offer_link(array(
                      'label' => $meta['wooLabel'][0],
                      'price' => $product->get_price(),
                      'link' => $product->get_permalink(), 
                      'background' => $color,
                      'color' => $this->getContrastColor($color),
                      'product_id' => $meta['linked_product'][0],
                      'event_date' => $event_start_date->format('D, M d Y @ H:i'),
                      'quantity' => 1
                    ));
                
              endif;
            endif;


          endif;
         endif;

       $html .= '</div>';




     $html .= '</div>';
   endif;
   return $html;

 }



 private function getContrastColor($hexcolor) {
     $r = hexdec(substr($hexcolor, 1, 2));
     $g = hexdec(substr($hexcolor, 3, 2));
     $b = hexdec(substr($hexcolor, 5, 2));
     $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
     return ($yiq >= 150) ? '#333' : '#fff';
 }


 public function get_calendar($calDate = '') {

  $this->setStartOfWeek($this->calendar_start_day);

   $eventDates = $this->get_sub_events();
   if($eventDates) :
     foreach ($eventDates as $key => $event) :

      //check if this is a sub_event of this event
      $parentID = wp_get_post_parent_id($event->ID);
      if($parentID == $this->eventID) :
        $child_event = true;
      else :
        $child_event = false;
      endif;

      
       $starttime = get_post_meta($event->ID, 'starttime', true);
       $endtime = get_post_meta($event->ID, 'endtime', true);
       $date = get_post_meta($event->ID, 'event_date', true);
       $color = $this->get_event_color($event->ID);
       $text_color = $this->getContrastColor($color);


      



       $insideHTML = '<div class="shadow-event ' . ($child_event ? 'event' : 'disable') . (MINDEVENTS_IS_MOBILE ? ' mobile' : '') . '">';
         $insideHTML .= '<span class="edit" style="color:' . $text_color . '; background:' . $color .';" data-subid="' . $event->ID . '">';
            if($child_event):
              $insideHTML .= $starttime . ' - ' . $endtime;
            else :
              $insideHTML .= '<a href="' . get_edit_post_link( $parentID) . '" target="_blank" style="color:' . $text_color . ';">';
                $insideHTML .= get_the_title($parentID);
              $insideHTML .= '</a>';
            endif;
           
         $insideHTML .= '</span>';

         if(is_admin() && $child_event) :
          $insideHTML .= '<span data-subid="' . $event->ID . '" class="delete">&#10005;</span>';
        endif;
         
       $insideHTML .= '</div>';
       $eventDates = $this->addDailyHtml($insideHTML, $date);
     endforeach;
   endif;
   return $this->render();
 }


 public function update_sub_event($sub_event, $meta, $parentID) {
   $unique = $this->build_unique_key($parentID, $meta['event_date'], $meta);
   $meta['event_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($meta['event_date'] . ' ' . $meta['starttime']) );
   $meta['event_start_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($meta['event_date'] . ' ' . $meta['starttime']) );
   $meta['event_end_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($meta['event_date'] . ' ' . $meta['endtime']) );
   $meta['unique_event_key'] = $unique;
   foreach ($meta as $key => $value) :
     update_post_meta($sub_event, $key, $value);
   endforeach;
 }




 public function add_sub_event($date, $meta, $eventID, $args = array()) {
   $unique = $this->build_unique_key($eventID, $date, $meta);
   $return = array();

   //check to see if the event already exists
   $args = array(
     'fields' => 'ids',
     'post_type'   => 'sub_event',
     'post_status' => 'publish',
     'meta_query'  => array(
       array(
         'key' => 'unique_event_key',
         'value' => $unique
       )
     )
   );
   $check_query = new WP_Query( $args );

   //if it doesnt exist, add it
   if( empty($check_query->have_posts()) ) :
     $terms = wp_get_post_terms( $eventID, 'event_category',  array('fields' => 'ids'));
     $meta['event_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['starttime']) );
     $meta['event_start_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['starttime']) );
     $meta['event_end_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['endtime']) );
     $meta['unique_event_key'] = $unique;
     $meta['event_date'] = $date;

     // $meta['linked_product'] = $meta['linked_product'];

     do_action(MINDEVENTS_PREPEND . 'before_add_sub_event', $eventID, $meta);

     $defaults = array(
       'post_author'           => get_current_user_id(),
       'post_content'          => '',
       'post_title'            => $this->build_title($eventID, $date, $meta),
       'post_excerpt'          => '',
       'post_status'           => 'publish',
       'post_type'             => 'sub_event',
       'post_parent'           => $this->eventID,
       'context'               => '',
       'meta_input'            => $meta,
       'tax_input'             => array(
         'event_category' => $terms
       )
     );
     $args = wp_parse_args($args, $defaults);
     $return = wp_insert_post($args);
     do_action(MINDEVENTS_PREPEND . 'after_add_sub_event', $eventID, $return, $meta);
     else :
       $return = false;
     endif;

     return $return;

   }


   private function build_unique_key($eventID, $date = '', $times = '') {
     return sanitize_title($eventID . '_' . $date . '_' . $times['starttime'] . '-' . $times['endtime']);
   }

   private function build_title($parentID, $date = '', $times = '') {
     $title = get_the_title($parentID) . ' | ' . $date . ' | ' . $times['starttime'] . '-' . $times['endtime'];
     return apply_filters('mind_events_title', $title, $date, $times, $this);
   }


   public function delete_sub_events() {
     $sub_events = $this->get_sub_events();
     if (is_array($sub_events) && count($sub_events) > 0) {
       foreach($sub_events as $event){
         do_action(MINDEVENTS_PREPEND . 'before_delete_sub_event', $event->ID);
         $return = wp_delete_post($event->ID);
         do_action(MINDEVENTS_PREPEND . 'after_delete_sub_event', $event->ID);
       }
       return true;
     }
     return false;
   }


   public function get_archive_url() {
     return get_post_type_archive_link('events');
   }



   public function generate_schema() {
     $sub_events = $this->get_sub_events();
     $schema = array(
       '@context' => 'https://schema.org',
       'type' => 'TheaterEvent',
       'name' => get_the_title($this->eventID),
       'startDate' => get_post_meta($this->eventID, 'first_event_date', true),
       'endDate' => get_post_meta($this->eventID, 'last_event_date', true),
       'location' => array(
         '@type' => 'Place',
         'name' => get_bloginfo('name'), //TODO: Add this to event options
         'address' => array( //TODO: Add all this to event options
           '@type' => 'PostalAddress',
           'name' => '',
           'addressLocality' => '',
           'postalCode' => '',
           'addressRegion' => '',
           'addressCountry' => '',
         ),
       ),
       'image' => array(
         get_the_post_thumbnail_url($this->eventID, 'medium')
       ),
       'description' => get_the_content('', false, $this->eventID),
       'performer' => array(
         '@type' => 'Organization',
         'url' => get_site_url(),
         'name' => get_bloginfo('name')
       )
     );
     if($sub_events) :
       foreach ($sub_events as $key => $event) :
         $offer_array = array();
         $offers = get_post_meta($event->ID, 'offers', true);
         if($offers) :

           foreach ($offers as $key => $offer) :
             $offer_array[] = array(
               '@type' => 'Offer',
               'url' => $offer['link'],
               'price' => $offer['price'],
               'priceCurrency' => 'USD',
               'availability' => 'https://schema.org/InStock', //TODO: add this to sub event options
               'validFrom' => get_the_date( 'Y-m-d H:i:s', $this->eventID)
             );
           endforeach;
         endif;

         $schema['subEvent'][] = array(
           '@context' => 'https://schema.org',
           'type' => 'TheaterEvent',
           'name' => get_the_title($this->eventID),
           'doorTime' => get_post_meta($event->ID, 'event_start_time_stamp', true),
           'startDate' => get_post_meta($event->ID, 'event_start_time_stamp', true),
           'endDate' => get_post_meta($event->ID, 'event_end_time_stamp', true),
           'location' => array(
             '@type' => 'Place',
             'name' => get_bloginfo('name'), //TODO: Add this to event options
             'address' => array( //TODO: Add all this to event options
               '@type' => 'PostalAddress',
               'name' => '',
               'addressLocality' => '',
               'postalCode' => '',
               'addressRegion' => '',
               'addressCountry' => '',
             ),
           ),
           'image' => array(
             get_the_post_thumbnail_url($this->eventID, 'medium')
           ),
           'description' => get_post_meta($event->ID, 'eventDescription', true),
           'offers' => $offer_array,
           'performer' => array(
             '@type' => 'Organization',
             'url' => get_site_url(),
             'name' => get_bloginfo('name')
           )
         );
       endforeach;
     endif;
     return json_encode($schema);
   }
 }