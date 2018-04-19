<?php
class Blogs_Controller extends Laclasse_Controller {
  /**
  * Selected Blog
  *
  * @var mixed
  */
 protected $blog;


  /**
  * Constructor
  */
  public function __construct() {
    parent::__construct();
    $this->rest_base = 'blogs';
  }
  /**
  * Register the routes for the objects of the controller.
  */
  public function register_routes() {
    // GET POST laclasse/v1/users
    register_rest_route( $this->namespace, '/' . $this->rest_base, array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_blogs' ),
        'permission_callback' => array( $this, 'get_blogs_permissions_check' ),
      ),
      // array(
      //   'methods'         => WP_REST_Server::CREATABLE,
      //   'callback'        => array( $this, 'create_blog' ),
      //   'permission_callback' => array( $this, 'create_blog_permissions_check' ),
      //   'args'            => $this->get_endpoint_args_for_blog_schema( true ),
      // ),
      array(
        'methods'         => WP_REST_Server::DELETABLE,
        'callback'        => array( $this, 'delete_blogs' ),
        'permission_callback' => array( $this, 'delete_blog_permissions_check' ),
      ),
      ) 
    );

    // GET POST PUT PATCH DELETE laclasse/v1/blogs/{id}
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_blog' ),
        'permission_callback' => array( $this, 'get_blog_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::EDITABLE,
        'callback'        => array( $this, 'update_blog' ),
        'permission_callback' => array( $this, 'update_blog_permissions_check' ),
      ),
      array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => array( $this, 'delete_blog' ),
        'permission_callback' => array( $this, 'delete_blog_permissions_check' ),
      ),
      )
    ); 

    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/users', array(
        array(
          'methods'         => WP_REST_Server::READABLE,
          'callback'        => array( $this, 'get_blog_profile' ),
          'permission_callback' => array( $this, 'get_blog_permissions_check' ),
        ),
        ) );
  }
      
  /**
  * Get a collection of blogs
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_blogs( $request ) {
    // TODO Better use of WP_Site_Query or get_sites in order to not get all the blogs in one sitting
    $query_params = $request->get_query_params();
    $blogs = get_cached_blogs();
    
		$seenBy = null;
		$seenByWp = null;
		if (isset($query_params['seen_by'])) {
			if ($query_params['seen_by'] == $this->ent_user->id) {
				$seenBy = $this->ent_user;
				$seenByWp = $this->wp_user;
			} else {
				$seenBy = get_ent_user($query_params['seen_by']);
				if ($seenBy != null)
					$seenByWp = get_wp_user_from_ent_user($seenBy);
			}
		}

		$data = [];
		foreach ($blogs as $blog) {
			if (!filter_blog($blog, $query_params))
				continue;
			// if seen_by is set, filter by what the given ENT user can see
			if (($seenByWp != null) && !has_read_right($seenBy, $seenByWp->ID, $blog))
				continue;
			if (($seenByWp == null) && ($seenBy != null) && !has_right($seenBy, $blog))
				continue;

			ensure_read_right($this->ent_user, $this->wp_user->ID, $blog);
			array_push($data, $blog);
    }
    return new WP_REST_Response( $data, 200 );
  }
  
  /**
  * Get one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_blog( $request ) {
    if ( $this->blog ) {
      $data = $this->prepare_blog_for_response( $this->blog, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }

  /**
  * Get blog blogs from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_blog_profile( $request ) {
    $params = $request->get_params();
    $blog = $this->get_user_by( $params['id'] );
    
    if ( $blog ) {
      $data = $this->get_user_blogs($blog->id);
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_Error( 'code', __( 'message', 'text-domain' ) );
    }
  }
  

  /**
  * Create one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function create_blog( $request ) {
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
    // $blog = $this->prepare_blog_for_database( $request );
    
    // if ( function_exists( 'slug_some_function_to_create_blog')  ) {
    //   $data = slug_some_function_to_create_blog( $blog );
    //   if ( is_array( $data ) ) {
    //     return new WP_REST_Response( $data, 200 );
    //   }
    // }
    
    return new WP_Error( 'cant-create', __( 'message', 'text-domain'), array( 'status' => 500 ) );
  }
  
  /**
  * Update one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function update_blog( $request ) {
    // $blog = $this->prepare_blog_for_database( $request );
    
    // if ( function_exists( 'slug_some_function_to_update_blog')  ) {
    //   $data = slug_some_function_to_update_blog( $blog );
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
  * Delete one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_blog( $request ) {
    $blog = $this->prepare_blog_for_database( $request );
    
    $user = $this->get_user_by( $blog );
    if ( $user ) {
      $deleted = delete_user( $user->id );
      if (  $deleted  ) {
        return new WP_REST_Response( true, 200 );
      }
    }
    
    return new WP_Error( 'cant-delete', __( 'message', 'text-domain'), array( 'status' => 404 ) );
  }
  
  /**
  * Delete several blogs from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_blogs( $request ) {
    $blogs = $this->prepare_blog_for_database( $request );
    if($blogs instanceof WP_Error)
      return  new WP_REST_Response( null, 400 );
    foreach ($blogs as $user_id) {
      $user = $this->get_user_by( $user_id );
      if ( $user )
        $deleted = $deleted && delete_user( $user->id );
    }
    if ( $deleted )
      return new WP_REST_Response( true, 200 );

    return new WP_REST_Response( null, 404 );
  }

  public function get_blog_from_request( $request ) {
    $blog_id = $this->prepare_blog_for_database( $request );   
    if(!( $blog_id instanceof WP_Error ) ) 
        return get_site($blog_id);
    return null;
  }


  /**
  * Check if a given request has access to get blogs
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_blogs_permissions_check( $request ) {
    
    return $this->is_user_logged_in( $request );
  }
        
  /**
  * Check if a given request has access to get a specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_blog_permissions_check( $request ) {
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);
    if( ! $this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( $this->blog && !has_read_right( $this->ent_user, $this->wp_user->id, $this->blog ))
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );

    return has_read_right( $this->ent_user, $this->wp_user->id, $this->blog );
  }
  
  /**
  * Check if a given request has access to create blogs
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function create_blog_permissions_check( $request ) { 
    return $this->is_user_logged_in( $request ) && has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Check if a given request has access to update a specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function update_blog_permissions_check( $request ) {
    return $this->is_user_logged_in( $request ) && has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Check if a given request has access to delete a specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function delete_blog_permissions_check( $request ) {
    return $this->is_user_logged_in( $request ) && has_admin_right( $this->ent_user, $this->wp_user->ID );
  }
  
  /**
  * Prepare the blog for create or update operation
  *
  * @param WP_REST_Request $request Request object
  * @return WP_Error|object $prepared_blog
  */
  protected function prepare_blog_for_database( $request ) {
    $url_params = $request->get_url_params();
    $json_params = $request->get_json_params();
    switch ($request->get_method()) {
      case 'DELETE':
        if( array_key_exists( 'id', $url_params ) && valid_number( $url_params['id'] ) )
          return $url_params['id'];
        if( is_array($json_params) && array_reduce($json_params, function($carry,$item) { return $carry && Users_Controller::valid_number($item);}, true))
          return $json_params;
        break;
      case 'GET':
        if( array_key_exists( 'id', $url_params ) && Users_Controller::valid_number( $url_params['id'] ) )
          return $url_params['id'];
        break;
      default:
        # code...
        break;
    }
    return new WP_Error( 'bad-request', __( 'message', 'text-domain'), array( 'status' => 400 ) );
  }

  /**
  * Retrieve the blog's users
  *
  * @param integer $blog_id Wordpress Blog ID
  * @return WP_Error|object $blogs
  */
  public function get_user_blogs($blog_id) {
		foreach ($user_blogs as $user_blog) {
      $data = []; 
			array_push($blogs, $data);
    }
    return $blogs;
  }

  /**
  * Prepare the blog for the REST response
  *
  * @param WP_User $blog WordPress representation of the blog.
  * @param WP_REST_Request $request Request object.
  * @return mixed
  */
  public function prepare_blog_for_response( $blog, $request ) {
    $response = blog_data($blog);
    $url_params = $request->get_url_params();
    if( array_key_exists( 'id', $url_params ) && Users_Controller::valid_number( $url_params['id'] ) ) {
      $args = [
        'blog_id' => $blog->id,
      ];
      $blog_users = get_users($args);
			$users = [];
			foreach ($blog_users as $blog_user) {
				$data = new stdClass();
				$data->id = $blog_user->ID;
				$data->user_id = $blog_user->ID;
				$data->blog_id = $blog->id;
				if (isset($blog_user->roles) && count($blog_user->roles) > 0)
					$data->role = $blog_user->roles[0];
				array_push($users, $data);
      }
      $response->users = $users;
    }
    return $response;
  }
  
}