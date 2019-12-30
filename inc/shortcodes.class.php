<?php

class mindEventsShortcodes {
  function __construct() {
    //Add Our Shorcodes
    add_shortcode( 'events', array($this, 'events'));

  }

  public function events($atts) {
    mapi_write_log($atts);

  }


}

new mindEventsShortcodes;
