<?php



class mindreturnsHelpers {
  function __construct() {

  }
  public function get_countries(){
    $WC_Countries = new WC_Countries();
    return $WC_Countries->get_allowed_countries();
  }
  public function get_states($country){
    $WC_Countries = new WC_Countries();
    return $WC_Countries->get_states($country);
  }
}
