<?php
class Users_Controller extends WP_REST_Controller {
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
    $this->rest_base = 'users';
  }
  /**
  * Register the routes for the objects of the controller.
  */
  public function register_routes() {
    // GET POST laclasse/v1/users
    register_rest_route( $this->namespace, '/' . $this->rest_base, array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_items' ),
        'permission_callback' => array( $this, 'get_items_permissions_check' ),
        'args'            => array(),
      ),
      // array(
      //   'methods'         => WP_REST_Server::CREATABLE,
      //   'callback'        => array( $this, 'create_item' ),
      //   'permission_callback' => array( $this, 'create_item_permissions_check' ),
      //   'args'            => $this->get_endpoint_args_for_item_schema( true ),
      // ),
      ) );


    // GET POST PUT PATCH DELETE laclasse/v1/users/{id}
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_item' ),
        'permission_callback' => array( $this, 'get_item_permissions_check' ),
        'args'            => array(),
      ),
      array(
        'methods'         => WP_REST_Server::EDITABLE,
        'callback'        => array( $this, 'update_item' ),
        'permission_callback' => array( $this, 'update_item_permissions_check' ),
        'args'            => $this->get_endpoint_args_for_item_schema( false ),
      ),
      array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => array( $this, 'delete_item' ),
        'permission_callback' => array( $this, 'delete_item_permissions_check' ),
        'args'     => array(
          'force'    => array(
            'default'      => false,
          ),
        ),
      ),
      )
    );  
  }
      
  /**
  * Get a collection of items
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_items( $request ) {
    $url_params = $request->get_query_params();
    
    if ( array_key_exists('id',$url_params) ) {
      $url_params['include'] = $url_params['id'];
      unset($url_params['id']);
    }
    if ( !array_key_exists('blog_id',$url_params) ) 
      $url_params['blog_id'] = '';
    if ( array_key_exists('limit',$url_params) ) {
      $url_params['number'] = $url_params['limit'];
      unset($url_params['limit']);
    }
    if ( array_key_exists('page',$url_params) ) {
      $url_params['paged'] = $url_params['page'];
      unset($url_params['page']);
    }
    if ( array_key_exists('sort_dir',$url_params) 
      && ( strcasecmp($url_params['sort_dir'], 'ASC') || strcasecmp($url_params['sort_dir'], 'DESC') ) ) {
      $url_params['order'] = $url_params['sort_dir'];
      unset($url_params['sort_dir']);
    }
    if ( array_key_exists('sort_col',$url_params) ) {
      $avaliable_order_cols = ['id', 'login', 'nicename', 'email', 'url', 'registered', 'display_name', 'post_count', 'include','ent_id','ent_profile'];
      if( in_array($url_params['sort_col'], $avaliable_order_cols) ) {
        switch ($url_params['sort_col']) {
          case 'ent_id':
            $url_params['orderby'] = 'meta_value';
            $url_params['meta_key'] = 'uid_ENT';
            break;
          case 'ent_profile':
            $url_params['orderby'] = 'meta_value';
            $url_params['meta_key'] = 'profile_ENT';
            break;
          default:
            $url_params['orderby'] = $url_params['sort_col'];
            break;
        }
        unset($url_params['sort_col']);
      }
    }
    if ( array_key_exists('query', $url_params)) {
      // Search only works for these params : email address, URL, ID, username or display_name
      // Does't work on meta_query cause you'd need several requests
      $url_params['search'] = '*'.esc_attr( $url_params['query'] ).'*';
      unset($url_params['query']);
    }
    $users = get_users($url_params);
    $data = array();
    foreach( $users as $user ) {
      $userData = $this->prepare_item_for_response( $user, $request );
      $data[] = $this->prepare_response_for_collection( $userData );
    }
    if( array_key_exists('number', $url_params) )  {
      $limit = $url_params['number'];
      unset($url_params['number']);
      $url_params['count_total'] = true;
      $total = (new WP_User_Query($url_params))->get_total();
      $data = (object) [
        'data' => $data,
        'page' => array_key_exists('paged', $url_params) ? $url_params['paged'] : 1, 
        'total' => ceil($total / $limit)
      ];
    }
    return new WP_REST_Response( $data, 200 );
  }
  
  /**
  * Get one item from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_item( $request ) {
    $params = $request->get_params();
    $item = get_user_by('id', $params['id']);
    
    if ( $item ) {
      $data = $this->prepare_item_for_response( $item, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }
  
  /**
  * Create one item from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function create_item( $request ) {
    // $item = $this->prepare_item_for_database( $request );
    
    // if ( function_exists( 'slug_some_function_to_create_item')  ) {
    //   $data = slug_some_function_to_create_item( $item );
    //   if ( is_array( $data ) ) {
    //     return new WP_REST_Response( $data, 200 );
    //   }
    // }
    
    return new WP_Error( 'cant-create', __( 'message', 'text-domain'), array( 'status' => 500 ) );
  }
  
  /**
  * Update one item from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function update_item( $request ) {
    $item = $this->prepare_item_for_database( $request );
    
    if ( function_exists( 'slug_some_function_to_update_item')  ) {
      $data = slug_some_function_to_update_item( $item );
      if ( is_array( $data ) ) {
        return new WP_REST_Response( $data, 200 );
      }
    }
    
    return new WP_Error( 'cant-update', __( 'message', 'text-domain'), array( 'status' => 500 ) );
  }
  
  /**
  * Delete one item from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_item( $request ) {
    $item = $this->prepare_item_for_database( $request );
    
    if ( function_exists( 'slug_some_function_to_delete_item')  ) {
      $deleted = slug_some_function_to_delete_item( $item );
      if (  $deleted  ) {
        return new WP_REST_Response( true, 200 );
      }
    }
    
    return new WP_Error( 'cant-delete', __( 'message', 'text-domain'), array( 'status' => 500 ) );
  }
  
  /**
   * Retrieves the Wordpress using from the ENT using the cookie
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_User|null
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
    return sync_ent_user_to_wp_user($this->ent_user, false);
  }
  /**
  * Check if a given request has access to get items
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_items_permissions_check( $request ) {
    // return true; //<--use to make readable by all
    if(!$this->wp_user) {
      $this->wp_user = $this->get_wp_user_from_ent($request);
    }
    return $this->wp_user ? true : false;
  }
        
  /**
  * Check if a given request has access to get a specific item
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_item_permissions_check( $request ) {
    return $this->get_items_permissions_check( $request );
  }
  
  /**
  * Check if a given request has access to create items
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function create_item_permissions_check( $request ) { 
    return new WP_Error( 'cant-create', __( 'message', 'text-domain'), array( 'status' => 405 ) );;
  }
  
  /**
  * Check if a given request has access to update a specific item
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function update_item_permissions_check( $request ) {
    return has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Check if a given request has access to delete a specific item
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function delete_item_permissions_check( $request ) {
    return $this->create_item_permissions_check( $request );
  }
  
  /**
  * Prepare the item for create or update operation
  *
  * @param WP_REST_Request $request Request object
  * @return WP_Error|object $prepared_item
  */
  protected function prepare_item_for_database( $request ) {
    return array();
  }
  
  /**
  * Prepare the item for the REST response
  *
  * @param mixed $item WordPress representation of the item.
  * @param WP_REST_Request $request Request object.
  * @return mixed
  */
  public function prepare_item_for_response( $item, $request ) {
    $user = new stdClass();
    $user->id = $item->ID;
    if (isset($item->data)) {
      $d = $item->data;
      if (isset($d->user_login))
        $user->login = $d->user_login;
      if (isset($d->user_email))
        $user->email = $d->user_email;
      if (isset($d->user_nicename))
        $user->nicename = $d->user_nicename;
      if (isset($d->display_name))
        $user->display_name = $d->display_name;
      if (isset($d->user_registered))
        $user->ctime = $d->user_registered;
      if (isset($d->deleted))
        $user->deleted = $d->deleted == 1;
    }
    $uid_ENT = get_user_meta($user->id, 'uid_ENT', true);
    if ($uid_ENT)
      $user->ent_id = $uid_ENT;

    $profile_ENT = get_user_meta($user->id, 'profile_ENT', true);
    if (isset($profile_ENT))
      $user->ent_profile = $profile_ENT;

    return $user;
  }
}