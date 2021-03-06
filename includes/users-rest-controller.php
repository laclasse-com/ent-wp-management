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
    // GET POST /users
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

    // GET /users/current
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/current', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_current' ),
        'permission_callback' => array( $this, 'get_user_permissions_check' ),
      ),
      )
    );

     // GET /users/cleanup
     register_rest_route( $this->namespace, '/' . $this->rest_base . '/cleanup', array(
      array(
        'methods'         => WP_REST_Server::READABLE ,
        'callback'        => array( $this, 'get_cleanup' ),
        'permission_callback' => array( $this, 'get_is_super_admin_permissions_check' ),
      ),
      )
    );

    // GET POST PUT DELETE /users/{id}
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

    // GET POST /users/{id}/blogs
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/blogs', array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_user_profiles' ),
        'permission_callback' => array( $this, 'get_user_permissions_check' ),
      ),
      array(
        'methods'         => WP_REST_Server::CREATABLE,
        'callback'        => array( $this, 'create_user_profile' ),
        'permission_callback' => array( $this, 'create_user_profile_permissions_check' ),
      ),
      )
    );


    // GET DELETE /users/{id}/blogs/{blog_id}
    register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9]+)' . '/blogs' . '/(?P<blog_id>[0-9]+)' , array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_user_profile' ),
        'permission_callback' => array( $this, 'get_user_profile_permissions_check' ),
      ),
      array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => array( $this, 'delete_user_profile' ),
        'permission_callback' => array( $this, 'delete_user_profile_permissions_check' ),
      ),
      )
    );
  }

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
    // A authenticated user can only access users
    // in blogs he has administrator priveledges
    if( !$this->ent_user->super_admin ) {
      $blogQuery = new Ent_Blog_Meta_Query( array(
        'wp_user' => $this->wp_user,
        'ent_user' => $this->ent_user,
        'role' => 'administrator'
      ) );

      $blogs = array_map( function( $blog ) { return $blog->blog_id; }, $blogQuery->get_results() );
      if( !isset( $blogs ) || !isset( $query_params['blog_id'] ) ) {
        return new WP_Error( 'not-found', null, array( 'status' => 404 ) );
      }

      if( !in_array( $query_params['blog_id'], $blogs ) ) {
        return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );
      }
    } else if ( !array_key_exists('blog_id',$query_params) ) {
        $query_params['blog_id'] = '';
    }

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

    $query_params['count_total'] = true;
    $userQuery = new WP_User_Query($query_params);
    $users = $userQuery->get_results();
    $data = array();
    foreach( $users as $user ) {
      $userData = $this->prepare_user_for_response( $user, $request );
      $data[] = $this->prepare_response_for_collection( $userData );
    }
    if( array_key_exists('number', $query_params) )  {
      $total = $userQuery->get_total();
      $data = (object) [
        'data' => $data,
        'page' => array_key_exists('paged', $query_params) ? intval($query_params['paged']) : 1,
        'total' => $total
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
    if( !$this->ent_user->super_admin ) {
      $blogQuery = new Ent_Blog_Meta_Query( array(
        'wp_user' => $this->wp_user,
        'ent_user' => $this->ent_user,
        'role' => 'administrator'
      ) );

      $blogs = array_map( function( $blog ) { return $blog->blog_id; }, $blogQuery->get_results() );

      if( !isset( $blogs ) || count($blogs) == 0 ) {
        return new WP_Error( 'not-found', null, array( 'status' => 404 ) );
      }
      foreach ($blogs as $blog) {
        $user = $this->get_user_by( $params['id'], $blog );
        if( $user ) { break; }
      }
    } else {
      $user = $this->get_user_by( $params['id'], $blogs );
    }



    if ( $user ) {
      $data = $this->prepare_user_for_response( $user, $request );
      return new WP_REST_Response( $data , 200 );
    } else {
      return new WP_REST_Response( null , 404 );
    }
  }

  /**
  * Get current user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_current( $request ) {
    $query_params = $request->get_query_params();
    if( array_key_exists('expand', $query_params) && $query_params['expand'] == true ){
      update_roles_wp_user_from_ent_user($this->wp_user, $this->ent_user);
    }
    $data = $this->prepare_user_for_response( $this->wp_user, $request );
    return new WP_REST_Response( $data , 200 );
  }

  /**
  * Get user blogs from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function get_user_profiles( $request ) {
    $query_params = $request->get_query_params();
    $params = $request->get_params();
    $user = $this->get_user_by( $params['id'] );

    if ( array_key_exists('limit',$query_params) ) {
      $limit = $query_params['limit'];
    }
    if ( array_key_exists('page',$query_params) ) {
      $page = $query_params['page'];
    }

    if ( $user ) {
      $data = $this->get_user_blogs($user->id);
      if( isset($limit) && Laclasse_Controller::valid_number($limit) && $limit > 0) {
        if( !isset($page) || $page <= 0 || !Laclasse_Controller::valid_number($page) )
          $page = 1;
      }

      if( isset($limit) && isset($page) ) {
        $offset = ($page - 1) * $limit;
        $data = (object) [
          'total' => count( $data ),
          'page' => intval( $page ),
          'data' => array_splice( $data, $offset, $limit ),
        ];
      }

      return new WP_REST_Response( $data , 200 );
    }

    return new WP_REST_Response( null, 404 );
  }


  /**
  * Create user from the collection
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Response
  */
  public function create_user( $request ) {
    $json = $this->get_json_from_request( $request );
    if ($json instanceof WP_Error )
      return new WP_REST_Response(null, 400);

    if( !isset($json->ent_id) )
      return new WP_REST_Response(null, 400);

    $data = [];
    // Users are created if needed using their ent_id
    if( is_array($json->ent_id) ) {
      foreach ( $json->ent_id as $user_ent_id ) {
        $user_ent = get_ent_user( $user_ent_id );
        if ($user_ent != null) {
          $user_wp = sync_ent_user_to_wp_user( $user_ent );
          array_push( $data, $this->prepare_user_for_response( $user_wp, $request ) );
        }
      }
    } else {
      $user_ent = get_ent_user( $json->ent_id );
      if  ($user_ent == null )
        return new WP_REST_Response( null, 404 );
      else {
        $user_wp = sync_ent_user_to_wp_user( $user_ent );
        $data = $this->prepare_user_for_response( $user_wp, $request );
      }
    }
    return new WP_REST_Response( $data, 200 );
  }

  /**
  * Update one user from the collection
  * Est-ce utile ?
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
    if( !$user_wp )
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
    $deleted = true;
    foreach ($users as $user_id) {
      $user = $this->get_user_by( $user_id );
      if ( $user )
        $deleted = $deleted && delete_user( $user->id );
    }
    if ( $deleted )
      return new WP_REST_Response( null, 200 );

    return new WP_REST_Response( null, 404 );
  }

  /**
  * Subscribe user to blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function create_user_profile( $request ) {
    $user_id = $this->get_id_from_request( $request );
    $json = $this->get_json_from_request( $request );

    $blog_id = $json->blog_id;
    $blog = get_blog($blog_id);

    if ( !has_admin_right($this->ent_user, $this->wp_user->ID, $blog) )
      unset($json->role);

    if ( !isset($json->role) )
      $json->role = get_user_blog_default_role($this->ent_user, $blog);

    $user = $this->get_user_by( $user_id );

    $success = add_user_to_blog($blog_id, $user->id, $json->role);
    if( $success instanceof WP_Error )
      return new WP_REST_Response( null, 404 );

    $data = new stdClass();
    $data->user_id = $user->id;
    $data->blog_id = $blog_id;
    $data->role = $json->role;
    $data->forced = false;

    return new WP_REST_Response( $data, 200 );
  }

  /**
  * Get one profile on specific blog
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|WP_REST_Request
  */
  public function get_user_profile( $request ) {
    $user_id = $this->get_id_from_request( $request );
    $blog_id = $request->get_url_params()['blog_id'];
    $user = $this->get_user_by( $params['id'], $blog_id );

    if ( $user ) {
      $ent_user = get_ent_user_from_user_id( $user->id );
      $blog = get_blog( $blog_id );
      $data = new stdClass();
      $data->user_id = $user->id;
      $data->blog_id = intval( $blog_id );
      if( count( $user->roles ) )
        $data->role = $user->roles[0];
      $data->forced = ($ent_user != null && is_forced_blog($blog, $ent_user));

      return new WP_REST_Response( $data , 200 );
    }

    return new WP_REST_Response( null, 404 );
  }

  public function delete_user_profile( $request ) {
    $user_id = $this->get_id_from_request( $request );
    $blog_id = $request->get_url_params()['blog_id'];

    remove_user_from_blog($user_id, $blog_id);
    return new WP_REST_Response( $data , 200 );
  }


  /**
  * Check if a given request has access to get users
  *
  * @param WP_REST_Request $request Full data about the request.
  * @return WP_Error|bool
  */
  public function get_users_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
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
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;
    if( !$this->is_user_logged_in($request) )
      return new WP_Error( 'unauthorized', null, array( 'status' => 401 ) );
    $json = $this->get_json_from_request($request);
    if(isset($json->ent_id)) {
      $best_profile = get_user_best_profile($this->ent_user);
      if(in_array( $best_profile, ['DIR','ADM', 'ENS','ETA','DOC','EVS'] ) ) {
        return true;
      } else if(!is_array($json->ent_id) && $json->ent_id == $this->ent_user->id ){
        return true;
      }
      return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );
    } else if(!has_admin_right( $this->ent_user, $this->wp_user->ID )) {
      return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );
    }
    if( !has_admin_right( $this->ent_user, $this->wp_user->ID ) && (
      !isset( $json->ent_id ) || $json->ent_id != $this->ent_user->id ) )
      return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );
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

  public function create_user_profile_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;

    $is_logged_in = $this->is_user_logged_in( $request );
    if( !$is_logged_in )
      return false;
    $user_id = $this->get_id_from_request( $request );
    $json = $this->get_json_from_request( $request );
    if( !isset($json->blog_id))
      return new WP_Error( 'bad-request', null, array( 'status' => 400 ) );
    $blog_id = $json->blog_id;
    $blog = get_blog($blog_id);
    if( !$blog )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );
    $user = $this->get_user_by($user_id);
    if ( !$user )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );

		if ( ! has_admin_right( $this->ent_user, $this->wp_user->ID, $blog ) ) {
			if ( ($this->wp_user->ID != $user->ID && $this->ent_user->id != $user_id ) || !has_read_right($this->ent_user, $this->wp_user->ID, $blog) )
				return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );
    }
    return true;
  }

  public function get_user_profile_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;

    if( !$this->is_user_logged_in( $request ) )
      return false;

    $user_id = $this->get_id_from_request( $request );
    $blog_id = $request->get_url_params()['blog_id'];

    $blog = get_blog($blog_id);
    if( !$blog )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );

    $user = $this->get_user_by($user_id);
    if ( !$user )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );

		if ( $this->wp_user->ID == $user_id || $this->ent_user->id == $user_id )
      update_roles_wp_user_from_ent_user( $this->wp_user, $this->ent_user );

    if ( !is_user_member_of_blog($user->ID, $blog_id) )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );

    if ( ! has_admin_right( $this->ent_user, $this->wp_user->ID, $blog ) ) {
      if ( ($this->wp_user->ID != $user->ID && $this->ent_user->id != $user_id ) )
        return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );
    }
    return true;
  }

  public function delete_user_profile_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;

    if( !$this->is_user_logged_in( $request ) )
      return false;

    $user_id = $this->get_id_from_request( $request );
    $blog_id = $request->get_url_params()['blog_id'];

    $blog = get_blog($blog_id);
    if( !$blog )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );

    $user = $this->get_user_by($user_id);
    if ( !$user )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );

    if ( !is_user_member_of_blog($user->ID, $blog_id) )
      return new WP_Error( 'not-found', null, array( 'status' => 404 ) );
    if ( !has_admin_right( $this->ent_user, $this->wp_user->ID, $blog )
      && ( $this->wp_user->ID != $user->ID && $this->ent_user->id != $user_id ) )
      return new WP_Error( 'forbidden', null, array( 'status' => 403 ) );

    return true;
  }

  public function get_is_super_admin_permissions_check( $request ) {
    if( $this->permission_checked )
      return true;
    $this->permission_checked = true;

    $is_logged_in = $this->is_user_logged_in( $request );
    if( !$is_logged_in )
      return false;

    return is_super_admin($this->wp_user->ID);
  }

  /**
   * This method lists or delete all users
   *
   * @param WP_REST_Request $request
   * @return WP_REST_Response
   */
  public function get_cleanup( $request ) {
    // Get Wordpress users
    global $wpdb;
    $db_prefix = $wpdb->base_prefix;
    $sqlQuery = "SELECT user_id, meta_value as uid_ENT "
    . "FROM {$db_prefix}usermeta "
    . "WHERE meta_key = 'uid_ENT';";

    $results = $wpdb->get_results($sqlQuery);
    if ($wpdb->last_error) {
      return new WP_REST_Response( 'Error while fetching Wordpress users' , 500 );
    }
    // Get ENT users and index them
    $keys = array();
    $batchEntIds = array();
    foreach ($results as $user) {
      $batchEntIds[] = $user->uid_ENT;
      if(count($batchEntIds) === 200) {
        $entUsers = $this->ask_for_ent_users($batchEntIds);
        $keys = array_merge($keys, array_map( function( $user ) { return $user->id; }, $entUsers ));
        $batchEntIds = array();
      }
    }
    if(count($batchEntIds) !== 0) {
      $entUsers = $this->ask_for_ent_users($batchEntIds);
      $keys = array_merge($keys, array_map( function( $user ) { return $user->id; }, $entUsers ));
    }

    // Récupération des utilisateurs don't l'id ent n'existe plus
    $deadUsers = array_filter( $results, function( $user ) use ($keys) {
      $index = array_search($user->uid_ENT, $keys);
      if($index !== false) {
        unset($keys[$index]);
      }
      return $index === false;
    } );

    $deadUsers = array_values($deadUsers);
    // Return early if no user were found
    if(count($deadUsers) === 0) {
      return new WP_REST_Response( 'No Wordpress user that aren\'t in laclasse' , 404 );
    }

    return new WP_REST_Response( $deadUsers , 200 );
  }

  /**
   * Sends HTTP request to fetch users based on given entIds
   *
   * @param array $entIds: id of users to fetch
   * @return mixed the json representation of users
   */
  private function ask_for_ent_users($entIds = array()) {
    $get = array('id' => $entIds);
    $query = preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($get));

    $url = ANNUAIRE_URL . "api/users?" . $query;
    $data = get_http($url, $error, $status);

    if($status !== 200) return null;
    return json_decode( $data );
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
      $blog = get_blog($user_blog->userblog_id);
				if ($blog == null)
          continue;

      $user = new WP_User( $wp_id, '', $user_blog->userblog_id );

			$data = new stdClass();
			$data->blog_id = $user_blog->userblog_id;
      $data->user_id = $wp_id;
      if( isset($user) && count($user->roles) )
			  $data->role = $user->roles[0];

      $data->forced = ($ent_user != null && is_forced_blog($blog, $ent_user));
			array_push($blogs, $data);
    }
    return $blogs;
  }

  /**
  * Prepare the user for the REST response
  * Note : An users blogs are shown if the owner is asking for it or if you have admin rights
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
      if (isset($d->user_registered)) {
        $datetime = new DateTime($d->user_registered);
        $response->registered = $datetime->format(DateTime::ATOM);
      }
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
    if( ( $user->ID == $this->wp_user->ID || has_admin_right($this->ent_user, $this->wp_user->ID) ) &&
        ( !array_key_exists('expand',$params) || ( array_key_exists('expand',$params) && filter_var( $params['expand'], FILTER_VALIDATE_BOOLEAN ) ) )
    )
    $response->blogs = $this->get_user_blogs($response->id); //TODO See if there's a way to reduce sql querues

    return $response;
  }
}
