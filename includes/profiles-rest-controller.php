<?php
class Profiles_Controller extends WP_REST_Controller {
  protected $test = false;
  /**
  * Constructor
  */
  public function __construct() {
    $this->namespace = 'laclasse/v1';
    $this->rest_base = 'profiles';
  }
  /**
  * Register the routes for the objects of the controller.
  */
  public function register_routes() {
    // GET POST laclasse/v1/profiles
    register_rest_route( $this->namespace, '/' . $this->rest_base, array(
      array(
        'methods'         => WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_items' ),
        'permission_callback' => array( $this, 'get_items_permissions_check' ),
        'args'            => array(
          
        ),
      ),
      array(
        'methods'         => WP_REST_Server::CREATABLE,
        'callback'        => array( $this, 'create_item' ),
        'permission_callback' => array( $this, 'create_item_permissions_check' ),
        'args'            => $this->get_endpoint_args_for_item_schema( true ),
      ),
      ) );


    // GET POST PUT PATCH DELETE laclasse/v1/profiles/{id}
      register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
        array(
          'methods'         => WP_REST_Server::READABLE,
          'callback'        => array( $this, 'get_item' ),
          'permission_callback' => array( $this, 'get_item_permissions_check' ),
          'args'            => array(
            'context'          => array(
              'default'      => 'view',
            ),
          ),
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
        ) );
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/schema', array(
          'methods'         => WP_REST_Server::READABLE,
          'callback'        => array( $this, 'get_public_item_schema' ),
          ) );
        }
        
        /**
        * Get a collection of items
        *
        * @param WP_REST_Request $request Full data about the request.
        * @return WP_Error|WP_REST_Response
        */
        public function get_items( $request ) {
          // $profiles = get_users(array( 'fields' => array('ID')));
          // $data = array();
          // foreach( $profiles as $profile ) {
          //   $itemdata = $this->prepare_item_for_response( $profile, $request );
          //   $data[] = $this->prepare_response_for_collection( $itemdata );
          // }
          global $wp_roles;

          $all_roles = $wp_roles->roles;
          $data = apply_filters('editable_roles', $all_roles);
          return new WP_REST_Response( $data, 200 );
        }
        
        /**
        * Get one item from the collection
        *
        * @param WP_REST_Request $request Full data about the request.
        * @return WP_Error|WP_REST_Response
        */
        public function get_item( $request ) {
          //get parameters from request
          $params = $request->get_params();
          $item = array();//do a query, call another class, etc
          $data = $this->prepare_item_for_response( $item, $request );
          
          //return a response or error based on some conditional
          if ( 1 == 1 ) {
            return new WP_REST_Response( $data, 200 );
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
          $item = $this->prepare_item_for_database( $request );
          
          if ( function_exists( 'slug_some_function_to_create_item')  ) {
            $data = slug_some_function_to_create_item( $item );
            if ( is_array( $data ) ) {
              return new WP_REST_Response( $data, 200 );
            }
          }
          
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
        * Check if a given request has access to get items
        *
        * @param WP_REST_Request $request Full data about the request.
        * @return WP_Error|bool
        */
        public function get_items_permissions_check( $request ) {
          if (!array_key_exists("LACLASSE_AUTH", $_COOKIE)) {
            http_response_code(401);
            exit;
          }
        
          // get the current session
          $error; $status;
          $session = get_http(ANNUAIRE_URL . "api/sessions/" . $_COOKIE["LACLASSE_AUTH"], $error, $status);
        
          if ($status != 200) {
            http_response_code(401);
            exit;
          }
        
          $session = json_decode($session);
        
          // get the user of the current session
        
          $userENT = get_ent_user($session->user);
          if ($userENT == null) {
            http_response_code(401);
            exit;
          }

          return true; //<--use to make readable by all
          //  return current_user_can( 'edit_something' );
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
          return current_user_can( 'edit_something' );
        }
        
        /**
        * Check if a given request has access to update a specific item
        *
        * @param WP_REST_Request $request Full data about the request.
        * @return WP_Error|bool
        */
        public function update_item_permissions_check( $request ) {
          return $this->create_item_permissions_check( $request );
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
          $response_item =   (object) [
            'id' => $item->ID ,
            'user_id' => $item->ID ,
            // 'blog_id' => $item->blog_id
          ];
          if (isset($item->roles) && count($item->roles) > 0)
          $response_item->role = $item->roles[0];
          
          return $response_item;
        }
      }