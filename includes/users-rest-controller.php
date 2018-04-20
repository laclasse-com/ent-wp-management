<?php
class Users_Controller extends Laclasse_Controller {
  /**
  * Constructor
  */
  public function __construct() {
    parent::__construct();
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
        'callback'        => array( $this, 'get_users' ),
        'permission_callback' => array( $this, 'get_users_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::CREATABLE,
        'callback'        => array( $this, 'create_user' ),
        'permission_callback' => array( $this, 'create_user_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::DELETABLE,
        'callback'        => array( $this, 'delete_users' ),
        'permission_callback' => array( $this, 'delete_user_permissions_check' ),
      ),
      ) 
    );

    // GET POST PUT PATCH DELETE laclasse/v1/users/{id}
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_user' ),
        'permission_callback' => array( $this, 'get_user_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::EDITABLE,
        'callback'        => array( $this, 'update_user' ),
        'permission_callback' => array( $this, 'update_user_permissions_check' ),
      ),
      array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => array( $this, 'delete_user' ),
        'permission_callback' => array( $this, 'delete_user_permissions_check' ),
      ),
      )
    ); 
    
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/current', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_current' ),
        'permission_callback' => array( $this, 'get_user_permissions_check' ),
      ),
      ) );

      register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/blogs', array(
        array(
          'methods'         => WP_REST_Server::READABLE,
          'callback'        => array( $this, 'get_user_profile' ),
          'permission_callback' => array( $this, 'get_user_permissions_check' ),
          'args'            => array(),
        ),
        ) );
        // TODO Move it
        register_rest_route( $this->namespace, '/setup' , array(
          array(
            'methods'         => WP_REST_Server::READABLE,
            'callback'        => array( $this, 'get_setup' ),
            'permission_callback' => array( $this, 'get_user_permissions_check' ),
            'args'            => array(),
          ),
          ) 
        );
  }
      
  public function get_setup($request) { return new WP_REST_Response( array("domain" => DOMAIN_CURRENT_SITE), 200 ); }
  /**
  * Get a collection of users
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_users( $request ) {
    $query_params = $request->get_query_params();
    
    if ( array_key_exists('id',$query_params) ) {
      $query_params['include'] = $query_params['id'];
      unset($query_params['id']);
    }
    if ( !array_key_exists('blog_id',$query_params) ) 
      $query_params['blog_id'] = '';
    if ( array_key_exists('limit',$query_params) ) {
      $query_params['number'] = $query_params['limit'];
      unset($query_params['limit']);
    }
    if ( array_key_exists('page',$query_params) ) {
      $query_params['paged'] = $query_params['page'];
      unset($query_params['page']);
    }
    if ( array_key_exists('sort_dir',$query_params) 
      && ( strcasecmp($query_params['sort_dir'], 'ASC') || strcasecmp($query_params['sort_dir'], 'DESC') ) ) {
      $query_params['order'] = $query_params['sort_dir'];
      unset($query_params['sort_dir']);
    }
    if ( array_key_exists('sort_col',$query_params) ) {
      $avaliable_order_cols = ['id', 'login', 'nicename', 'email', 'url', 'registered', 'display_name', 'post_count', 'include','ent_id','ent_profile'];
      if( in_array($query_params['sort_col'], $avaliable_order_cols) ) {
        switch ($query_params['sort_col']) {
          case 'ent_id':
            $query_params['orderby'] = 'meta_value';
            $query_params['meta_key'] = 'uid_ENT';
            break;
          case 'ent_profile':
            $query_params['orderby'] = 'meta_value';
            $query_params['meta_key'] = 'profile_ENT';
            break;
          default:
            $query_params['orderby'] = $query_params['sort_col'];
            break;
        }
        unset($query_params['sort_col']);
      }
    }
    if ( array_key_exists('query', $query_params)) {
      // Search only works for these params : email address, URL, ID, username or display_name
      // Does't work on meta_query cause you'd need several requests
      $query_params['search'] = '*'.esc_attr( $query_params['query'] ).'*';
      unset($query_params['query']);
    }
    $users = get_users($query_params);
    $data = array();
    foreach( $users as $user ) {
      $userData = $this->prepare_user_for_response( $user, $request );
      $data[] = $this->prepare_response_for_collection( $userData );
    }
    if( array_key_exists('number', $query_params) )  {
      $limit = $query_params['number'];
      unset($query_params['number']);
      $query_params['count_total'] = true;
      $total = (new WP_User_Query($query_params))->get_total();
      $data = (object) [
        'data' => $data,
        'page' => array_key_exists('paged', $query_params) ? $query_params['paged'] : 1, 
        'total' => ceil($total / $limit)
      ];
    }
    return new WP_REST_Response( $data, 200 );
  }
  
  /**
  * Get one user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_user( $request ) {
    $params = $request->get_params();
    $user = $this->get_user_by( $params['id'] );
    
    if ( $user ) {
      $data = $this->prepare_user_for_response( $user, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_REST_Response( null , 400 );
    }
  }
  
  /**
  * Get current user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_current( $request ) {
    if ( $this->wp_user ) {
      $data = $this->prepare_user_for_response( $this->wp_user, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }

  /**
  * Get user blogs from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_user_profile( $request ) {
    $params = $request->get_params();
    $user = $this->get_user_by( $params['id'] );
    
    if ( $user ) {
      $data = $this->get_user_blogs($user->id);
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }
  

  /**
  * Create one user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function create_user( $request ) {
    $json = $this->get_json_from_request( $request );
    if ($json instanceof WP_Error || !isset($json->login) 
      || !isset($json->display_name) || !isset($json->login) || !isset($json->display_name) || !isset($json->email) )
      return new WP_Response(null, 400);
    
    $password = substr(md5(microtime()), rand(0,26), 20);
		$user_id = wp_create_user($json->login, $password);
		// remove the user for blog 1
		remove_user_from_blog($user_id, 1);
    
    $user_data = array('ID' => $user_id);
    $user_data['display_name'] = $json->display_name;
    $user_data['user_email'] = $json->email;

    update_user_meta($user_id, 'uid_ENT', $json->ent_id);
		if (isset($json->ent_profile))
			update_user_meta($user_id, 'profile_ENT', $json->ent_profile);

		wp_update_user($user_data);

		$user_wp = $this->get_user_by( $user_id );
		$data = $this->prepare_user_for_response( $user_wp );
    
    return new WP_Response( $data, 200 );
  }
  
  /**
  * Update one user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function update_user( $request ) {
    $user_id = $this->get_id_from_request( $request );
    $json = $this->get_json_from_request( $request );
    
    if ( $json instanceof WP_Error || $user_id instanceof WP_Error )
      return new WP_REST_Response( null, 400 );
      
    $user_wp = $this->get_user_by($user_id);
    if( $user_wp )
      return new WP_REST_Response( null, 404 ); 

    if (isset($json->ent_id))
      update_user_meta($user_wp->ID, 'uid_ENT', $json->ent_id);
    if (isset($json->ent_profile))
      update_user_meta($user_wp->ID, 'profile_ENT', $json->ent_profile);
  
    $user_data = array('ID' => $user_wp->ID);
    if (isset($json->display_name))
      $user_data['display_name'] = $json->display_name;
    if (isset($json->email))
      $user_data['user_email'] = $json->email;
    wp_update_user($user_data);
    $user_wp = get_user_by('id', $user_id);
    $data = $this->prepare_user_for_response($user_wp);

    return new WP_REST_Response( $data, 200 ); 
  }
  
  /**
  * Delete one user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_user( $request ) {
    $user_id = $this->get_id_from_request( $request );
    $deleted = delete_user( $user_id );
    if ( $deleted )
      return new WP_REST_Response( null, 200 );
    
    return new WP_REST_Response( null, 404 );
  }
  
  /**
  * Delete several users from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_users( $request ) {
    $users = $this->get_json_from_request( $request );
    if($users instanceof WP_Error)
      return  new WP_REST_Response( null, 400 );
    foreach ($users as $user_id) {
      $user = $this->get_user_by( $user_id );
      if ( $user )
        $deleted = $deleted && delete_user( $user->id );
    }
    if ( $deleted )
      return new WP_REST_Response( true, 200 );

    return new WP_REST_Response( null, 404 );
  }
   /**
   * Retrieves the Wordpress user if it exists 
   *
   * @param integer|string $some_id Can be the user's Wordpress ID or ent ID
   * @return WP_User|null
   */
  public function get_user_by($some_id) {
    if( Laclasse_Controller::valid_number($some_id) )
      return get_user_by( 'id', $some_id );
    return reset( get_users( array( 'meta_key' => 'uid_ENT', 'meta_value' => $some_id, 'blog_id' => '' ) ) );
  }

  /**
  * Check if a given request has access to get users
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_users_permissions_check( $request ) {
    return $this->is_user_logged_in($request);
  }
        
  /**
  * Check if a given request has access to get a specific user
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_user_permissions_check( $request ) {
    return $this->get_users_permissions_check( $request );
  }
  
  /**
  * Check if a given request has access to create users
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function create_user_permissions_check( $request ) { 
    if( !$this->is_user_logged_in($request) ) 
      return new WP_Error( 'unauthorized', null, 401 );
    if( !has_admin_right( $this->ent_user, $this->wp_user->ID ) )
      return new WP_Error( 'forbidden', null, 403 );
    return true;
  }
  
  /**
  * Check if a given request has access to update a specific user
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function update_user_permissions_check( $request ) {
    return $this->create_user_permissions_check( $request );
  }
  
  /**
  * Check if a given request has access to delete a specific user
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function delete_user_permissions_check( $request ) {
    return $this->create_user_permissions_check( $request );
  }
  
  /**
  * Retrieve the user's blogs
  *
  * @param integer $wp_id Wordpress User ID
  * @return WP_Error|object $blogs
  */
  public function get_user_blogs($wp_id) {
    $user_blogs = get_blogs_of_user($wp_id);
    $ent_user = get_ent_user_from_user_id($wp_id);
    $blogs = [];
		foreach ($user_blogs as $user_blog) {
      $user = new WP_User( $wp_id, $user_blog->userblog_id );
      if( !$user ) 
        continue;

			$data = new stdClass();
			$data->id = "$wp_id-$user_blog->userblog_id" ;
			$data->blog_id = $user_blog->userblog_id;
      $data->user_id = $wp_id;
      if(count($user->roles))
			  $data->role = $user->roles[0];
		
      $data->forced = ($ent_user != null && is_forced_blog($blog, $ent_user)); // Doesn't work; isn't for current 
			array_push($blogs, $data);
    }
    return $blogs;
  }

  /**
  * Prepare the user for the REST response
  *
  * @param WP_User $user WordPress representation of the user.
  * @param WP_REST_Request $request Request object.
  * @return mixed
  */
  public function prepare_user_for_response( $user, $request ) {
    $response = new stdClass();
    $response->id = $user->ID;
    if (isset($user->data)) {
      $d = $user->data;
      if (isset($d->user_login))
        $response->login = $d->user_login;
      if (isset($d->user_email))
        $response->email = $d->user_email;
      if (isset($d->user_nicename))
        $response->nicename = $d->user_nicename;
      if (isset($d->display_name))
        $response->display_name = $d->display_name;
      if (isset($d->user_registered))
        $response->ctime = $d->user_registered;
      if (isset($d->deleted))
        $response->deleted = $d->deleted == 1;
    }
    $uid_ENT = get_user_meta($user->id, 'uid_ENT', true);
    if ($uid_ENT)
      $response->ent_id = $uid_ENT;

    $profile_ENT = get_user_meta($user->id, 'profile_ENT', true);
    if (isset($profile_ENT))
      $response->ent_profile = $profile_ENT;

    $params = $request->get_params();
    if( !array_key_exists('expand',$params) || ( array_key_exists('expand',$params) && filter_var( $params['expand'], FILTER_VALIDATE_BOOLEAN ) ) ) 
      $response->blogs = $this->get_user_blogs($response->id); //TODO See if there's a way to reduce sql querues
    
    return $response;
  }
  
}