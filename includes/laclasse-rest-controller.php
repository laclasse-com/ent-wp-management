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
   * Tells if permissions were checked, this variable exists
   * because permission_callbacks can be called multiple times
   * for one API call
   *
   * If permissions were checked, you don't need to check it
   *
   * @var boolean
   */
  protected $permission_checked = false;

  /**
  * Constructor
  */
  public function __construct() {
    $this->namespace = 'laclasse/v1';
  }

  /**
  * Retrieves the Wordpress user if it exists
  *
  * @param integer|string $some_id Can be the user's Wordpress ID or ent ID
  * @param integer|string $blog_id
  * @return WP_User|null
  */
  public function get_user_by($some_id, $blog_id = '') {
    if( Laclasse_Controller::valid_number($some_id) )
      return reset( get_users( array( 'include' => array($some_id), 'blog_id' => $blog_id ) ) );
    return reset( get_users( array( 'login' => $some_id, 'blog_id' => $blog_id ) ) );
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
   * User's email
   *
   * @return void
   */
  public function get_user_email() {
    if(!$this->ent_user)
      return null;
    $user_email;
    foreach($this->ent_user->emails as $email) {
      if (!isset($user_email) || $email->primary)
        $user_email = $email->address;
    }
    if (!isset($user_email))
      $user_email = $userENT->id . '@noemail.lan';

    return $user_email;
  }

  /**
  * Retrieve json for from the WP_REST_Request object
  *
  * @param WP_REST_Request $request Request object
  * @return WP_Error|object $prepared_blog
  */
  protected function get_json_from_request( $request ) {
    $url_params = $request->get_url_params();
    $json_params = /*$request->get_json_params();
    if( !$json_params )
      $json_params =*/ json_decode($request->get_body());
    switch ($request->get_method()) {
      case 'DELETE':
        if( is_array($json_params) && array_reduce($json_params, function($carry,$item) { return $carry && Laclasse_Controller::valid_number($item);}, true))
          return $json_params;
        break;
      case 'POST':
        // if( is_array( $json_params ) ) {
        //   return array_map( function($json) { return (object) $json; }, $json_params );
        // }
        // return (object) $json_params;
      case 'PUT':
        return /*(object)*/ $json_params;
      default:
        break;
    }
    return new WP_Error( 'bad-request', __( 'message', 'text-domain'), array( 'status' => 400 ) );
  }


  protected function get_id_from_request( $request ) {
    $url_params = $request->get_url_params();
    if( array_key_exists( 'id', $url_params ) )
          return $url_params['id'];
    return new WP_Error( 'bad-request', __( 'message', 'text-domain'), array( 'status' => 400 ) );
  }

  /**
   * Determine if the value is a integer or a string that can be converted to an integer
   *
   * @param integer|string $value
   * @return boolean valid number
   */
  public static function valid_number($value) {
    return is_int($value) || ctype_digit($value);
  }
}