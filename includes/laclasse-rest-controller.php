<?php
class Laclasse_Controller extends WP_REST_Controller {
  /**
   * Wordpress user
   *
   * @var WP_User
   */
  protected $wp_user;
  /**
   * ENT user
   *
   * @var mixed
   */
  protected $ent_user;


  /**
  * Constructor
  */
  public function __construct() {
    $this->namespace = 'laclasse/v1';
  }

  /**
   * Retrieves the Wordpress user from the ENT using the cookie
   *
   * @param WP_REST_Request $request Full data about the request.
   */
  public function get_wp_user_from_ent($request) {
    if (!array_key_exists("LACLASSE_AUTH", $_COOKIE)) 
      return null;
    
  
    // get the current session
    $error; $status;
    $session = get_http(ANNUAIRE_URL . "api/sessions/" . $_COOKIE["LACLASSE_AUTH"], $error, $status);
  
    if ($status != 200) 
      return null;
    
  
    $session = json_decode($session);
  
    // get the user of the current session
    $this->ent_user = get_ent_user($session->user);
    if ( !$this->ent_user ) 
      return null;  
  
    // get/create and update the corresponding WP user
    $this->wp_user = sync_ent_user_to_wp_user($this->ent_user, false);
  }
  
  /**
  * Check if a user is logged in
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return bool
  */
  public function is_user_logged_in( $request ) {
    if( !$this->wp_user )
      $this->get_wp_user_from_ent($request);
    return $this->wp_user ? true : false;
  }
  
  /**
   * Determine if the value is a integer or a string that can be converted to an integer
   * TODO Move this to an utils class
   * @param integer|string $value
   * @return boolean valid number
   */
  public static function valid_number($value) {
    return is_int($value) || ctype_digit($value);
  }
}


add_filter( 'rest_request_after_callbacks', 'laclasse_rest_request_after_callbacks');

function laclasse_rest_request_after_callbacks( $response ) {
  if( $response instanceof WP_Error ) {
    $error_data = $response->get_error_data();
    if ( is_array( $error_data ) && isset( $error_data['status'] ) )
      $status = $error_data['status'];
    else
      $status = 500;  
    return new WP_REST_Response(null,$status);
  }
  return $response;
}