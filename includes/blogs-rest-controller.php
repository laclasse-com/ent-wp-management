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
    // GET POST DELETE /blogs
    register_rest_route( $this->namespace, '/' . $this->rest_base, array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_blogs' ),
        'permission_callback' => array( $this, 'get_blogs_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::CREATABLE,
        'callback'        => array( $this, 'create_blog' ),
        'permission_callback' => array( $this, 'create_blog_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::DELETABLE,
        'callback'        => array( $this, 'delete_blogs' ),
        'permission_callback' => array( $this, 'delete_blogs_permissions_check' ),
      ),
      ) 
    );

    // GET POST PUT DELETE /blogs/{id}
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[0-9]+)', array(
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
    // GET POST DELETE /blogs/{id}/users
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/users', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_blog_users' ),
        'permission_callback' => array( $this, 'get_blog_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::CREATABLE,
        'callback'        => array( $this, 'create_blog_user' ),
        'permission_callback' => array( $this, 'create_blog_user_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::DELETABLE,
        'callback'        => array( $this, 'delete_blog_users' ),
        'permission_callback' => array( $this, 'delete_blog_users_permissions_check' ),
      ),
      )
    );

     // DELETE /blogs/{id}/users/{user_id}
     register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/users' . '/(?P<user_id>[0-9]+)' , array(
      array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => array( $this, 'delete_blog_user' ),
        'permission_callback' => array( $this, 'delete_blog_user_permissions_check' ),
      ),
      )
    ); 

    // GET SETUP
    register_rest_route( $this->namespace, '/setup' , array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_setup' ),
        'permission_callback' => array( $this, 'is_user_logged_in' ),
      ),
      ) 
    );
  }
      
  /**
   * Return the domain of the Wordpress installation
   *
   * @param WP_REST_Request $request
   * @return array
   */
  public function get_setup( $request ) { 
    return new WP_REST_Response( array( "domain" => DOMAIN_CURRENT_SITE ), 200 );
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
    $data = $this->prepare_blog_for_response( $this->blog, $request );
    return new WP_REST_Response( $data , 200 );
  }

  /**
  * Get blog users from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_blog_users( $request ) {
    $blog_id = $this->blog->id;
    $blog_users = get_users(array('blog_id' => $blog_id));
		$data = [];
		foreach ($blog_users as $blog_user) {
			$user = new stdClass();
			// $user->id = "$blog_user->ID-$blog_id";
			$user->user_id = $blog_user->ID;
			$user->blog_id = $blog_id;
			if (isset($blog_user->roles) && count($blog_user->roles) > 0)
				$user->role = $blog_user->roles[0];
			array_push($data, $user);
		}
    
    return new WP_REST_Response( $data, 200);
  }
  

  /**
  * Create one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function create_blog( $request ) {
    $json = $this->get_json_from_request($request);

    if( !isset($json->name) || !isset($json->domain) || !isset($json->type) 
      || !in_array($json->type, ['ETB','CLS','GRP','GPL','ENV']) )
      return new WP_REST_Response( null, 400 );

		// create the blog and add the WP user as administrator
		$blog_id = creerNouveauBlog(
			$json->domain, '/', $json->name, $userENT->login, $this->get_user_email(), 1,
			$this->wp_user->ID, $json->type, $json->structure_id, $json->group_id,
			$json->description);

		if (isset($json->quota_max))
			update_blog_option($blog_id, 'blog_upload_space', ceil($json->quota_max / MB_IN_BYTES));

		$blog = get_site($blog_id);
		if ($blog == null)
      return new WP_REST_Response( null, 404 );
    
    $data = $this->prepare_blog_for_response( $blog, $request);
    return new WP_REST_Response( $data, 200 );
  }
  
  /**
  * Update one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function update_blog( $request ) {
    $blog_id = $this->get_id_from_request( $request );
    $json = $this->get_json_from_request( $request );

		if (isset($json->name))
			update_blog_option($blog_id, 'blogname', $json->name);
		if (isset($json->description))
			update_blog_option($blog_id, 'blogdescription', $json->description);
		if (isset($json->type) && in_array($json->type, ['ETB','CLS','GRP','GPL','ENV']))
			update_blog_option($blog_id, 'type_de_blog', $json->type);
		if (isset($json->archived))
			update_blog_status($blog_id, 'archived', $json->archived ? '1' : '0');
		if (isset($json->deleted))
			update_blog_status($blog_id, 'deleted', $json->deleted ? '1' : '0');
		if (isset($json->structure_id))
			update_blog_option($blog_id, 'etablissement_ENT', $json->structure_id);
		if (isset($json->group_id))
			update_blog_option($blog_id, 'group_id_ENT', $json->group_id);
		if (isset($json->quota_max))
			update_blog_option($blog_id, 'blog_upload_space', ceil($json->quota_max / (1024 * 1024)));

    $blog = get_site($blog_id);
    $data = $this->prepare_blog_for_response( $blog, $request);
		return new WP_REST_Response( $data, 200);
  }
  
  /**
  * Delete one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_blog( $request ) {
    $blog_id = $this->get_id_from_request( $request );
    $deleted = delete_blog( $blog_id );
    if ( $deleted )
      return new WP_REST_Response( null, 200 );
    
    return new WP_REST_Response( null, 404 );
  }
  
  /**
  * Delete several blogs from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function delete_blogs( $request ) {
    $deleted = true;
    $blog_ids = $this->get_json_from_request( $request );
    foreach ($blog_ids as $blog_id) {
      $deleted = $deleted && delete_blog( $blog_id );
    }
    if ( $deleted )
      return new WP_REST_Response( true, 200 );

    return new WP_REST_Response( null, 404 );
  }

  public function create_blog_user( $request ) {
    $json = $this->get_json_from_request( $request );
    $blog_id = $this->get_id_from_request( $request );
    $has_admin_right = has_admin_right($this->ent_user, $this->wp_user->ID, $this->blog);
    
    if ( !is_array($json) )
      $json = [ $json ]; 
    $data = [];
    foreach ( $json as $blog_user ) {
			if ( !$has_admin_right ) 
				unset( $blog_user->role );
      
			if ( !isset( $blog_user->role ) ) {
				$userENT = get_ent_user_from_user_id($blog_user->user_id);
				if ( $userENT != null )
					$user_role = get_user_blog_default_role($userENT, $this->blog);
			}
      
      if ( isset( $blog_user->role ) ) {
				add_user_to_blog( $blog_id, $blog_user->user_id, $blog_user->role );
				$blog_result = new stdClass();
				$blog_result->id = $blog_user->user_id;
				$blog_result->user_id = $blog_user->user_id;
				$blog_result->blog_id = $blog_id;
				$blog_result->role = $blog_user->role;
				array_push( $data, $blog_result );
			}
    }
    if ( !is_array($json) ) 
        $data = count($data) > 0 ? $result[0] : null;

    return new WP_REST_Response( $data, 200 );
  }

  public function delete_blog_user( $request ) {
    $user_id = $request->get_url_params()['user_id'];
    $blog_id = $this->get_id_from_request( $request );
        
		remove_user_from_blog($user_id, $blog_id);

    return WP_REST_Response( null, 200 );
  }

  public function delete_blog_users( $request ) {
    $json = $this->get_json_from_request( $request );
    $blog_id = $this->get_id_from_request( $request );
        
    if( !is_array($json) ) 
      $json = [ $json ];
    
    foreach ( $json as $user_id ) {
      if( $this->get_user_by( $user_id, $blog_id ) )
        remove_user_from_blog( $user_id, $blog_id );
    }
    
    return WP_REST_Response( null, 200 );
  }

  /**
   * Retrieve a WP_Site object if it exists from the request
   *
   * @param WP_REST_Request $request
   * @return Wp_Site|null 
   */
  public function get_blog_from_request( $request ) {
    $blog_id = $this->get_id_from_request( $request );   
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
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    return $this->is_user_logged_in( $request );
  }
        
  /**
  * Check if a given request has access to get a specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_blog_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);
    if( ! $this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->blog )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if( !has_read_right( $this->ent_user, $this->wp_user->id, $this->blog ))
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    return true;
  }
  
  /**
  * Check if a given request has access to create blogs
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function create_blog_permissions_check( $request ) { 
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
  
    $blog = $this->get_json_from_request( $request );
    
    // Find if user can create blog
    if (in_array( get_user_best_profile($this->ent_user) , ['TUT','ELV']))
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    if($blog->type != 'ENV' &&  !has_admin_right( $this->ent_user, $this->wp_user->ID, $blog ) )
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );

    return true;
  }
  
  /**
  * Check if a given request has access to update a specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function update_blog_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);

    if( !$this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->blog )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if( !has_admin_right($this->ent_user, $this->wp_user->ID, $this->blog) )
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    return true;
  }
  
  /**
  * Check if a given request has access to delete blogs
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function delete_blogs_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( ! $this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );

    $json_ids = $this->get_json_from_request( $request );
    if($json_ids instanceof WP_Error)
      return $json_ids;
  
    foreach($json_ids as $blog_id) {
			$blog = get_blog($blog_id);
			if( !has_admin_right($this->ent_user, $this->wp_user->ID, $blog) )
        return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    }
    return true;
  }

  /**
  * Check if a given request has access to delete a specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function delete_blog_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);

    if( ! $this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->blog )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if( !has_admin_right($this->ent_user, $this->wp_user->ID, $this->blog) )
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    return true;
  } 

  public function create_blog_user_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);
    
    if( ! $this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->blog )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );

    $has_admin_right = has_admin_right($this->ent_user, $this->wp_user->ID, $this->blog);
    $json_array = $this->get_json_from_request( $request );
    if( !is_array( $json_array ) ) 
      $json_array = [ $json_array ];
    foreach ($json_array as $json) 
      if (!$has_admin_right && ( $this->wp_user->ID != $json->user_id || !has_read_right($this->ent_user, $this->wp_user->ID, $this->blog ) ) )
        return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );

    return true;
  }

  public function delete_blog_user_permissions_check( $request ) {
    if( $this->permission_checked)
      return true;
    $this->permission_checked = true;
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);

    if( !$this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->blog )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    
    $user_id = $request->get_url_params()['user_id'];
    if( !$user_id )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    $user = $this->get_user_by( $user_id, $blog_id );
    if( !$user)
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if ( !has_admin_right( $this->ent_user, $this->wp_user->ID, $this->blog )
      && ($this->wp_user->ID != $user_id || !has_read_right($this->ent_user, $this->wp_user->ID, $this->blog)) )
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    return true;
  }

  public function delete_blog_users_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->blog ) 
      $this->blog = $this->get_blog_from_request($request);

    if( !$this->is_user_logged_in( $request ) ) 
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->blog )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    
    return true;
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
    if( array_key_exists( 'id', $url_params ) && Laclasse_Controller::valid_number( $url_params['id'] ) 
     || ( $request->get_method() == 'POST' && $request->get_route() == '/blogs') ) {
      $args = [
        'blog_id' => $blog->id,
      ];
      $blog_users = get_users($args);
			$users = [];
			foreach ($blog_users as $blog_user) {
				$data = new stdClass();
				// $data->id = $blog_user->ID;
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