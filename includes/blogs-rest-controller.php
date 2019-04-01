<?php
class Blogs_Controller extends Laclasse_Controller {
  /**
  * Selected Blog
  *
  * @var mixed
  */
  protected $wp_site;


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
     register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[0-9]+)' . '/users' . '/(?P<user_id>[A-Za-z0-9]+)' , array(
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
    $query_params = $request->get_query_params();

    // Transforms query parameters to have the same use as the Laclasse's Directory API
    if ( array_key_exists('id',$query_params) ) {
      $query_params['blog_id'] = $query_params['id'];
      unset($query_params['id']);
    }

    if ( array_key_exists('limit',$query_params) ) {
      $query_params['number'] = $query_params['limit'];
      unset($query_params['limit']);
    }
    if ( array_key_exists('page',$query_params) ) {
      $page = $query_params['page'];
      if((!is_int($page) && !ctype_digit($page)) || (int)$page < 1)
        $page = 1;
      $query_params['paged'] = $page;
      unset($query_params['page']);
    }

    if ( array_key_exists('sort_dir',$query_params) ) {
      $query_params['order'] = $query_params['sort_dir'];
      unset($query_params['sort_dir']);
    }
    if ( array_key_exists('sort_col',$query_params) ) {
      $query_params['orderby'] = $query_params['sort_col'];
      unset($query_params['sort_col']);
    }

    if (isset($query_params['query'])) {
      $query_params['search'] = $query_params['query'];
      unset($query_params['query']);
    }

    if(isset($query_params['structure_id']) && is_array($query_params['structure_id'])) {
      $query_params['structure__in'] = $query_params['structure_id'];
      unset($query_params['structure_id']);
    }

    if(isset($query_params['group_id']) && is_array($query_params['group_id'])) {
      $query_params['group__in'] = $query_params['group_id'];
      unset($query_params['group_id']);
    }

    if (isset($query_params['seen_by'])) {
      if ($query_params['seen_by'] == $this->ent_user->id) {
				$query_params['ent_user'] = $this->ent_user;
				$query_params['wp_user'] = $this->wp_user;
			} else {
				$seenBy = get_ent_user($query_params['seen_by']);
				if ($seenBy != null) {
          $query_params['ent_user'] = $seenBy;
          $query_params['wp_user'] = get_wp_user_from_ent_user($seenBy);
        }
			}
    }

    // If orderby and order are an array of the same length, they are combined
    if( isset( $query_params['orderby'] ) && is_array( $query_params['orderby'] ) ) {
      if( !is_array($query_params['order'] ) || count( $query_params['order'] ) !== count( $query_params['orderby'] ) )
        return new WP_REST_Response( null, 400 );

      $query_params['orderby'] = array_combine($query_params['orderby'], $query_params['order']);
      unset($query_params['order']);
    }

    // If orderby is a quota, there's a performance hit because we need all the blogs
    if ( array_key_exists('orderby',$query_params)
      && ( in_array( $query_params['orderby'], array( 'quota_used', 'quota_max' ) )
        || isset( $query_params['orderby']['quota_used'] ) || isset( $query_params['orderby']['quota_max'] ) ) ) {
      $need_post_process = true;
      $original_number = $query_params['number'];
      $original_paged = $query_params['paged'];
      $query_params['number'] = 0x5f3759df;
      $query_params['paged'] = 1;
    }

    $query_params['return_blogs'] = true;

    $blogQuery = new Ent_Blog_Meta_Query( $query_params );
    $blogs = $blogQuery->get_results();

    $need_post_process = $need_post_process && $blogQuery->get_total() > 0;
    // Post processing done to order by quota_used, quota_max
    if( $need_post_process ) {
      $blog_ids = get_sites( array(
        'fields' => 'ids',
        'number' => $query_params['number'],
        'site__not_in' => 1,
      ) );

      $orderby = $query_params['orderby'];

      // Step 1: Get the needed quota
      $order_quota_used = array_key_exists( 'quota_used', $orderby );
      $order_quota_max = array_key_exists( 'quota_max', $orderby );
      foreach ($blog_ids as $blog_id) {
        switch_to_blog( $blog_id );
        $blog = array();
        if( $order_quota_used ) {
          $blog['quota_used'] = get_space_used();
        }
        if( $order_quota_max ) {
          $blog['quota_max'] = intval( get_space_allowed() );
        }
        $blog_sizes[$blog_id] = $blog;
        restore_current_blog();
      }

      // Step 2: Add them to blogs
      foreach ( $blogs as $site ) {
        if( $order_quota_used ) {
          $site->quota_used = $blog_sizes[$site->blog_id]['quota_used'];
        }
        if( $order_quota_max ) {
          $site->quota_max = $blog_sizes[$site->blog_id]['quota_max'];
        }
      }

      // Step 3: Sort the array using call_user_func_array( 'array_multisort', $params );
      $params = array();
      foreach ($orderby as $key => $value) {
        $params[] = array_column($blogs, $key);
        $params[] = $value == 'desc' ? SORT_DESC : SORT_ASC;
      }
      $params[] = &$blogs;
      call_user_func_array( 'array_multisort', $params );

      // Step 4: Retrieve the correct number of results
      $query_params['number'] = $original_number;
      $query_params['paged'] = $original_paged;
      $offset = ( $original_paged - 1 ) * $original_number;
      $blogs = array_splice( $blogs, $offset, $original_number );
    }

    foreach ($blogs as $key => $value) {
      $blogs[$key] = $this->prepare_blog_for_response( $value, $request );
    }

    if ( $query_params['number'] != null )
      $data = (object) [
        'total' => $blogQuery->get_total(),
        'limit' => intval( $query_params['number'] ),
        'page' => intval( $query_params['paged'] ),
        'data' => $blogs,
      ];
    else
      $data = $blogs;

    return new WP_REST_Response( $data, 200 );
  }

  /**
  * Get one blog from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_blog( $request ) {
    $data = $this->prepare_blog_for_response( $this->wp_site, $request );
    return new WP_REST_Response( $data , 200 );
  }

  /**
  * Get blog users from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_blog_users( $request ) {
    $blog_id = $this->wp_site->id;
    $query_params = $request->get_query_params();

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
    if ( array_key_exists('query', $query_params) ) {
      // Search only works for these params : email address, URL, ID, username or display_name
      // And specified below meta_query fields
      $query_params['search'] = '*'.esc_attr( $query_params['query'] ).'*';
      $initial_query = $query_params['query'];
      $query_params['meta_query'] = array(
        'relation' => 'OR',
        array(
          'key'     => 'uid_ENT',
          'value'   => $query_params['query'],
          'compare' => 'LIKE'
        ),
      );

      $query_params['_meta_or_search'] = true;
      unset($query_params['query']);
    }

    if( array_key_exists('ent_id', $query_params) ) {
      $query_params['meta_query'] = array(
        'relation' => 'OR',
        array(
          'key'     => 'uid_ENT',
          'value'   => $query_params['ent_id'] ,
          'compare' => 'IN'
        ),
      );
      unset( $query_params['ent_id'] );
    }

    $query_params['blog_id'] = $blog_id;
    $query_params['count_total'] = true;

    $userQuery = (new WP_User_Query($query_params));
    $blog_users = $userQuery->get_results();

		$data = [];
		foreach ($blog_users as $blog_user) {
			$user = new stdClass();
			$user->user_id = $blog_user->ID;
			$user->blog_id = $blog_id;
			if (isset($blog_user->roles) && count($blog_user->roles) > 0)
				$user->role = $blog_user->roles[0];
			array_push($data, $user);
		}

    if( array_key_exists('number', $query_params) )  {
      $total = $userQuery->get_total();
      $data = (object) [
        'data' => $data,
        'page' => array_key_exists('paged', $query_params) ? intval($query_params['paged']) : 1,
        'total' => $total
      ];
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

    // Check if the domain is valid
    $subdomain = explode( DOMAIN_CURRENT_SITE, $json->domain );
    if( $subdomain === false || count( $subdomain ) === 0
      || substr( $subdomain[0], -1 ) !== '.' || !ctype_lower( substr( $subdomain[0], 0, -1 ) )
      || domain_exists( $json->domain, '/' ) )
      return new WP_REST_Response( null, 400 );

    if( !isset($json->name) || !isset($json->domain) || !isset($json->type)
      || !in_array($json->type, ['ETB','CLS','GRP','GPL','ENV']) )
      return new WP_REST_Response( null, 400 );

    // Check blog type
    if($json->type == 'ETB' ) {
      if(!isset($json->structure_id) || isset($json->group_id))
        return new WP_REST_Response( null, 400 );
    } else if(in_array($json->type, ['CLS','GRP'])){
      if(!isset($json->structure_id) || !isset($json->group_id))
        return new WP_REST_Response( null, 400 );
    } else if($json->type == 'GPL') {
      if(!isset($json->group_id))
        return new WP_REST_Response( null, 400 );
    } else {
      if(isset($json->structure_id) || isset($json->group_id))
        return new WP_REST_Response( null, 400 );
    }

		// create the blog and add the WP user as administrator
		$blog_id = creerNouveauBlog(
			$json->domain, '/', $json->name, $this->ent_user->login, $this->get_user_email(), 1,
			$this->wp_user->ID, $json->type, $json->structure_id, $json->group_id,
			$json->description);

    // Create Ent_Blog_Meta_Model
    $entBlogMeta = new Ent_Blog_Meta_Model((object)[
      'group_id' => $json->group_id,
      'type' => $json->type,
      'structure_id' => $json->structure_id,
      'blog_id' => $blog_id,
      'name' => $json->name,
    ]);

    Ent_Blog_Meta_Model::wp_insert_or_update($entBlogMeta);

		if (isset($json->quota_max) && $this->ent_user->super_admin )
			update_blog_option($blog_id, 'blog_upload_space', ceil($json->quota_max / MB_IN_BYTES));
    if (isset($json->comments_enabled))
      update_blog_option($blog_id, 'default_comment_status', $json->comments_enabled ? 'open': 'closed');
    if (isset($json->discourage_index))
      update_blog_option($blog_id, 'blog_public', $json->discourage_index ?  1 : 0);
    if (isset($json->student_privacy))
      update_blog_option($blog_id, 'student-privacy', $json->student_privacy ? 1 : 0);
    if (isset($json->force_login) && $json->force_login) {
      switch_to_blog($blog_id);
      activate_plugin(WP_FORCE_LOGIN);
      restore_current_blog();
    }

    if(isset($json->users) && is_array($json->users)) {
      foreach($json->users as $user) {
        if($user->id == $this->wp_user->ID)
          continue;
        // Add user to blog
        add_user_to_blog( $blog_id, $user->user_id, $user->role );
      }
    }

    $blogs = new Ent_Blog_Meta_Query( array( 'blog_id' => $blog_id, 'return_blogs' => true ) );
    if( $blogs->get_total() == 0 )
      return new WP_REST_Response( null, 404 );

    $blog = $blogs->get_results()[0];
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

    switch_to_blog($blog_id);
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
		if (property_exists($json,'structure_id'))
			update_blog_option($blog_id, 'etablissement_ENT', $json->structure_id);
		if (property_exists($json, 'group_id'))
			update_blog_option($blog_id, 'group_id_ENT', $json->group_id);
		if (isset($json->quota_max) && $this->ent_user->super_admin)
      update_blog_option($blog_id, 'blog_upload_space', ceil($json->quota_max / (1024 * 1024)));
    if (isset($json->comments_enabled))
      update_blog_option($blog_id, 'default_comment_status', $json->comments_enabled ? 'open': 'closed');
    if (isset($json->discourage_index))
      update_blog_option($blog_id, 'blog_public', $json->discourage_index ?  1 : 0);
    if (isset($json->student_privacy))
      update_blog_option($blog_id, 'student-privacy', $json->student_privacy ? 1 : 0);
    if (isset($json->force_login)) {
      if($json->force_login)
        activate_plugin(WP_FORCE_LOGIN);
      else
        deactivate_plugins(WP_FORCE_LOGIN,false,false);
    }
    restore_current_blog();
    if ( isset($json->users) )
      $this->merge_blog_user_change( $request, $json->users );

    // Update Blog Ent Meta
    $results = new Ent_Blog_Meta_Query( array( 'blog_id' => $blog_id ) );

    if( $results->get_total() == 0 )  {
      $blog_options = get_blog_options($blog_id, array('etablissement_ENT', 'type_de_blog', 'group_id_ENT', 'blogname'));

      $entBlogMeta = new Ent_Blog_Meta_Model((object)[
        'structure_id' => $blog_options->etablissement_ENT,
        'type' => $blog_options->type_de_blog,
        'group_id' => $blog_options->group_id_ENT,
        'blog_id' => $blog_id,
        'name' => $json->name,
      ]);

    } else {
      $entBlogMeta = $results->get_results()[0];
      if (isset($json->type) && in_array($json->type, ['ETB','CLS','GRP','GPL','ENV']))
        $entBlogMeta->type = $json->type;
      if (property_exists($json,'structure_id'))
        $entBlogMeta->structure_id = $json->structure_id;
      if (property_exists($json,'group_id'))
        $entBlogMeta->group_id = $json->group_id;
      if (property_exists($json,'name'))
        $entBlogMeta->name = $json->name;
    }

    Ent_Blog_Meta_Model::wp_insert_or_update($entBlogMeta);

    $blogs = new Ent_Blog_Meta_Query( array( 'blog_id' => $blog_id, 'return_blogs' => true ) );
    if( $blogs->get_total() == 0 )
      return new WP_REST_Response( null, 404 );

    $blog = $blogs->get_results()[0];
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
    $has_admin_right = has_admin_right($this->ent_user, $this->wp_user->ID, $this->wp_site);

    if ( !is_array($json) )
      $json = [ $json ];
    $data = [];
    foreach ( $json as $blog_user ) {
			if ( !$has_admin_right )
				unset( $blog_user->role );

			if ( !isset( $blog_user->role ) ) {
        if( $blog_user->user_id != $this->wp_user->ID ) {
          $userENT = get_ent_user_from_user_id($blog_user->user_id);
        } else {
          $userENT = $this->ent_user;
        }
        if( $userENT != null )
          $blog_user->role = get_user_blog_default_role($userENT, $this->wp_site);
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

    if( $this->get_user_by( $user_id, $blog_id ) )
		  remove_user_from_blog( $user_id, $blog_id );

    return new WP_REST_Response( null, 200 );
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

    return new WP_REST_Response( null, 200 );
  }

  /**
   * This function applies the merge data for members of a given blog
   *
   * It function assumes the caller has admin rights,
   * which should be checked at the time this is called
   *
   * @param WP_REST_Request $request
   * @param object $json
   * @return void
   */
  private function merge_blog_user_change( $request, $json ) {
    $blog_id = $this->get_id_from_request( $request );
    $diff = $json->diff;
    if( !isset($diff) ) {
      return;
    }

    if( isset( $diff->add ) ) {
      foreach( $diff->add as $user_to_add ) {
				add_user_to_blog( $blog_id, $user_to_add->user_id, $user_to_add->role );
			}
    }

    if( isset( $diff->change ) ) {
      foreach( $diff->change as $user_to_change ) {
        if ( isset( $user_to_change->role ) ) {
				  add_user_to_blog( $blog_id, $user_to_change->user_id, $user_to_change->role );
				}
      }
    }

    if( isset( $diff->remove ) ) {
      foreach( $diff->remove as $user_to_delete ) {
        remove_user_from_blog( $user_to_delete->user_id, $blog_id );
      }
    }
  }

  /**
   * Retrieve a WP_Site object if it exists from the request
   *
   * @param WP_REST_Request $request
   * @return Wp_Site|null
   */
  public function get_blog_from_request( $request ) {
    $blog_id = $this->get_id_from_request( $request );
    if(!( $blog_id instanceof WP_Error ) ) {
      $blogQuery = new Ent_Blog_Meta_Query( array(
        'blog_id'           => $blog_id,
        'return_blogs'      => true,
        'count_total'       => false,
      ) );
      $blogs = $blogQuery->get_results();
      if( !empty( $blogs ) )
        return $blogs[0];

      return get_blog($blog_id);
    }

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
    $is_logged_in = $this->is_user_logged_in( $request );
    if( !$is_logged_in ) {
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    }

    if (isset($query_params['seen_by'])) {
      if ( $query_params['seen_by'] != $this->ent_user->id && !$this->ent_user->super_admin ) {
        return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
			}
    }

    return $is_logged_in;
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
    if( !$this->wp_site )
      $this->wp_site = $this->get_blog_from_request($request);
    if( ! $this->is_user_logged_in( $request ) )
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->wp_site )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if( !has_read_right( $this->ent_user, $this->wp_user->id, $this->wp_site ))
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
    if( !$this->wp_site )
      $this->wp_site = $this->get_blog_from_request($request);

    if( !$this->is_user_logged_in( $request ) )
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->wp_site )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if( !has_admin_right($this->ent_user, $this->wp_user->ID, $this->wp_site ) )
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
    if( !$this->wp_site )
      $this->wp_site = $this->get_blog_from_request($request);

    if( ! $this->is_user_logged_in( $request ) )
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->wp_site )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );

    if( !has_admin_right($this->ent_user, $this->wp_user->ID, $this->wp_site ) )
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    return true;
  }

  public function create_blog_user_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->wp_site )
      $this->wp_site = $this->get_blog_from_request($request);

    if( ! $this->is_user_logged_in( $request ) )
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->wp_site )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );

    $has_admin_right = has_admin_right($this->ent_user, $this->wp_user->ID, $this->wp_site);
    $json_array = $this->get_json_from_request( $request );
    if( !is_array( $json_array ) )
      $json_array = [ $json_array ];
    foreach ($json_array as $json)
      if (!$has_admin_right && ( $this->wp_user->ID != $json->user_id || !has_read_right($this->ent_user, $this->wp_user->ID, $this->wp_site ) ) )
        return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );

    return true;
  }

  public function delete_blog_user_permissions_check( $request ) {
    if( $this->permission_checked)
      return true;
    $this->permission_checked = true;
    if( !$this->wp_site )
      $this->wp_site = $this->get_blog_from_request($request);

    if( !$this->is_user_logged_in( $request ) )
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->wp_site )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );

    $user_id = $request->get_url_params()['user_id'];
    if( !$user_id )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    $user = $this->get_user_by( $user_id, $blog_id );
    if( !$user)
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );
    if ( !has_admin_right( $this->ent_user, $this->wp_user->ID, $this->wp_site )
      && ($this->wp_user->ID != $user_id || !has_read_right($this->ent_user, $this->wp_user->ID, $this->wp_site )) )
      return new WP_Error( 'forbidden', __( 'message', 'text-domain'), array( 'status' => 403 ) );
    return true;
  }

  public function delete_blog_users_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->wp_site )
      $this->wp_site = $this->get_blog_from_request($request);

    if( !$this->is_user_logged_in( $request ) )
      return new WP_Error( 'unauthorized', __( 'message', 'text-domain'), array( 'status' => 401 ) );
    if( !$this->wp_site )
      return new WP_Error( 'not found', __( 'message', 'text-domain'), array( 'status' => 404 ) );

    return true;
  }
  /**
  * Prepare the blog for the REST response
  *
  * @param WP_Site $blog WordPress representation of the blog.
  * @param WP_REST_Request $request Request object.
  * @return mixed
  */
  public function prepare_blog_for_response( $blog, $request = null ) {
    $expand = true;
    if($request == null) { $expand = false;}
    else {
      $url_params = $request->get_url_params();
      $query_params = $request->get_query_params();
    }

    if(isset($query_params['expand']) && filter_var($query_params['expand'], FILTER_VALIDATE_BOOLEAN) == false )  { $expand = false; }
    $response = $this->blog_data($blog, $expand );

    if( $expand && ( array_key_exists( 'id', $url_params ) && Laclasse_Controller::valid_number( $url_params['id'] )
     || ( $request->get_method() == 'POST' && $request->get_route() == '/blogs') ) ) {
      $args = [
        'blog_id' => $blog->id,
      ];
      $blog_users = get_users($args);
			$users = [];
			foreach ($blog_users as $blog_user) {
				$data = new stdClass();
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

  function blog_data($blogWp, $expand = false) {
    $result = array();
    foreach($blogWp as $k => $v) { $result[$k] = $v; }

    $result['id'] = intval($result['blog_id']);
    unset($result['blog_id']);
    $result['public'] = $result['public'] == 1;
    $result['archived'] = $result['archived'] == 1;
    $result['mature'] = $result['mature'] == 1;
    $result['spam'] = $result['spam'] == 1;
    $result['deleted'] = $result['deleted'] == 1;
    $scheme = is_ssl() ? 'https' : 'http';
    $result['url'] = "{$scheme}://{$blogWp->domain}{$blogWp->path}";
    if(is_bool($expand) && $expand ) {
      $result = array_merge( $result,  $this->extended_options($result['id'] ) );
    }

    return (object) $result;
  }

  function extended_options($blog_id) {
    $opts = array('admin_email', 'blogdescription',
    'student-privacy','default_comment_status', 'blog_public');

    $expanded_options = get_blog_options($blog_id, $opts);

    $result = array();
    $result['description'] = $expanded_options->blogdescription;

    $result['student_privacy'] = isset( $expanded_options->{'student-privacy'} ) && $expanded_options->{'student-privacy'};
    $result['comments_enabled'] = $expanded_options->default_comment_status == "open";
    $result['discourage_index'] = $expanded_options->blog_public == 1;

    switch_to_blog($blog_id);
    $result['quota_max'] = intval(get_space_allowed() * 1024 * 1024);
    $result['quota_used'] = intval(get_space_used() * 1024 * 1024);
    if(function_exists('is_plugin_active'))
      $result['force_login'] = is_plugin_active(WP_FORCE_LOGIN);
    restore_current_blog();

    return $result;
  }
}