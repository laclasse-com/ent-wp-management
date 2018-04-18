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
      ) 
    );

    // GET POST PUT PATCH DELETE laclasse/v1/users/{id}
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)', array(
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
    
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/current', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_current' ),
        'permission_callback' => array( $this, 'get_item_permissions_check' ),
        'args'            => array(),
      ),
      ) );

      register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/blogs', array(
        array(
          'methods'         => WP_REST_Server::READABLE,
          'callback'        => array( $this, 'get_item_profile' ),
          'permission_callback' => array( $this, 'get_item_permissions_check' ),
          'args'            => array(),
        ),
        ) );
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
    $item = $this->get_user_by( $params['id'] );
    
    if ( $item ) {
      $data = $this->prepare_item_for_response( $item, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }
  
  /**
  * Get current item from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_current( $request ) {
    if ( $this->wp_user ) {
      $data = $this->prepare_item_for_response( $this->wp_user, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }

  /**
  * Get item blogs from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_item_profile( $request ) {
    $params = $request->get_params();
    $item = $this->get_user_by( $params['id'] );
    
    if ( $item ) {
      $data = $this->get_user_blogs($item->id);
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
    /*
    // check rights
		ensure_admin_right($userENT, $userWp->ID);

		$json = json_decode(file_get_contents('php://input'));

		if (is_object($json) && isset($json->login)) {
			$password = substr(md5(microtime()), rand(0,26), 20);
			$user_id = wp_create_user($json->login, $password);
			// remove the user for blog 1
			remove_user_from_blog($user_id, 1);
	
			if (isset($json->ent_id))
				update_user_meta($user_id, 'uid_ENT', $json->ent_id);
			if (isset($json->ent_profile))
				update_user_meta($user_id, 'profile_ENT', $json->ent_profile);

			$user_data = array('ID' => $user_id);
			if (isset($json->display_name))
				$user_data['display_name'] = $json->display_name;
			if (isset($json->email))
				$user_data['user_email'] = $json->email;
			wp_update_user($user_data);

			$userWp = get_user_by('id', $user_id);
			$result = user_data($userWp);
		}
    */
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
    // $item = $this->prepare_item_for_database( $request );
    
    // if ( function_exists( 'slug_some_function_to_update_item')  ) {
    //   $data = slug_some_function_to_update_item( $item );
    //   if ( is_array( $data ) ) {
    //     return new WP_REST_Response( $data, 200 );
    //   }
    // }
    /*
    $json = json_decode(file_get_contents('php://input'));

		$user_id = intval($tpath[1]);
		$userWp = get_user_by('id', $user_id);
		if ($userWp == false)
			http_response_code(404);
		else {
			if (isset($json->ent_id))
				update_user_meta($userWp->ID, 'uid_ENT', $json->ent_id);
			if (isset($json->ent_profile))
				update_user_meta($userWp->ID, 'profile_ENT', $json->ent_profile);
		
			$user_data = array('ID' => $userWp->ID);

			if (isset($json->display_name))
				$user_data['display_name'] = $json->display_name;
			if (isset($json->email))
				$user_data['user_email'] = $json->email;
			wp_update_user($user_data);
			$userWp = get_user_by('id', $user_id);
			$result = user_data($userWp);
    }
     */
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
    
    $user = $this->get_user_by( $item );
    if ( $user ) {
      $deleted = delete_user( $user->id );
      if (  $deleted  ) {
        return new WP_REST_Response( true, 200 );
      }
    }
    
    return new WP_Error( 'cant-delete', __( 'message', 'text-domain'), array( 'status' => 404 ) );
  }
  
   /**
   * Retrieves the Wordpress user if it exists 
   *
   * @param integer|string $some_id Can be the user's Wordpress ID or ent ID
   * @return WP_User|null
   */
  public function get_user_by($some_id) {
    if(is_int($some_id) || ctype_digit($some_id))
      return get_user_by( 'id', $some_id );
    return reset( get_users( array( 'meta_key' => 'uid_ENT', 'meta_value' => $some_id ) ) );
  }

  /**
   * Retrieves the Wordpress user from the ENT using the cookie
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
    if(!$this->wp_user)
      $this->wp_user = $this->get_wp_user_from_ent($request);
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
    if(!$this->wp_user)
      $this->wp_user = $this->get_wp_user_from_ent($request);
    return $this->wp_user && has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Check if a given request has access to update a specific item
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function update_item_permissions_check( $request ) {
    if(!$this->wp_user)
      $this->wp_user = $this->get_wp_user_from_ent($request);
    return $this->wp_user && has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Check if a given request has access to delete a specific item
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function delete_item_permissions_check( $request ) {
    if(!$this->wp_user)
      $this->wp_user = $this->get_wp_user_from_ent($request);
    return $this->wp_user && has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Prepare the item for create or update operation
  *
  * @param WP_REST_Request $request Request object
  * @return WP_Error|object $prepared_item
  */
  protected function prepare_item_for_database( $request ) {
    switch ($request->get_method()) {
      case 'DELETE':
        return $request->get_url_params()['id'];
        break;
      
      default:
        # code...
        break;
    }
    return array();
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
			$blog = get_blog($user_blog->userblog_id); // Switch get_blog to a get_sites approch in order to only do one request
			if ($blog == null)
        continue;
        
			$data = new stdClass();
			$data->id = "$wp_id-$user_blog->userblog_id" ;
			$data->blog_id = $user_blog->userblog_id;
			$data->user_id = $wp_id;
			// try to find the user role
			$users_search = get_users(
				array(
					'blog_id' => $user_blog->userblog_id,
					'search'  => $wp_id
				)
			);
			if (count($users_search) > 0 && count($users_search[0]->roles) > 0)
        $data->role = $users_search[0]->roles[0];
  
			$data->forced = ($ent_user != null && is_forced_blog($blog, $ent_user)); // Doesn't work; isn't for current 
			array_push($blogs, $data);
    }
    return $blogs;
  }

  /**
  * Prepare the item for the REST response
  *
  * @param WP_User $item WordPress representation of the item.
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

    $params = $request->get_params();
    if( !array_key_exists('expand',$params) || ( array_key_exists('expand',$params) && filter_var( $params['expand'], FILTER_VALIDATE_BOOLEAN ) ) ) {
      // $user_blogs = get_blogs_of_user($user->id);
      // $user->blogs_users = $user_blogs;
      // $user_blogs_id = array_column($user_blogs,'userblog_id');
      // $user->sites = get_sites( array('site__in' => $user_blogs_id) ); 
    
      // $user->blogs = array_map(function($blog){ return get_blog($blog->userblog_id); }, $user_blogs );
      
      $user->blogs = $this->get_user_blogs($user->id); //TODO See if there's a way to reduce sql querues
    }
    return $user;
  }
  
}