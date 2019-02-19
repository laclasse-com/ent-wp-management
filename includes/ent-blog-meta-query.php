<?php
/**
 * Class used for querying ent blog meta which are special data
 * needed to link blogs to laclasse.com.
 *
 * This class helps getting around the problem that each blog metadata
 * are stored in a separate table called wp_%d_option therefore greatly
 * slowing down query time.
 *
 * This class also has a method to directly fetching blogs using a join query
 * between Ent_Blog_Meta_Query::$table_name and blogs in the same way the WP_Site_Query class
 *
 * It relies on $wpdb->prefix Ent_Blog_Meta_Query::$table_name being present in the database
 *
 * @see Ent_Blog_Meta_Query::prepare_query() for information on accepted arguments.
 */
class Ent_Blog_Meta_Query {

    /**
     * Name of the table
     *
     * @var string
     */
    public static $table_name = 'ent_options';

    /**
     * Query vars, after parsing
     *
     * @var array
     */
    public $query_vars = array();

    /**
     * List of found ent_blog_meta ids
     *
     * @var array
     */
    private $results;

    /**
     * Total number of found results for the current query
     *
     * @var int
     */
    private $total_found = 0;

    /**
     * The SQL query used to fetch matching ent blog meta.
     *
     * @var string
     */
    public $request;

    // SQL clauses
    public $query_fields;
    public $query_from;
    public $query_where;
    public $query_orderby;
    public $query_limit;

    /**
     * Constructor.
     *
     * @param null|string|array $query Optional. The query variables.
     */
    public function __construct( $query = null ) {
        if ( ! empty( $query ) ) {
            $this->prepare_query( $query );
            $this->query();
        }
    }

    /**
     * Fills in missing query variables with default values.
     *
     * @param array $args Query vars, as passed to `Ent_Blog_Meta_Query`.
     * @return array Complete query variables with undefined ones filled in with defaults.
     */
    public static function fill_query_vars( $args ) {
        $defaults = array(
            'blog_id'           => '',
            'structure_id'      => '',
            'return_blogs'      => false,
            'structure__in'     => array(),
            'structure__not_in' => array(),
            'relation'          => 'AND',
            'group_id'          => '',
            'group__in'         => array(),
            'group__not_in'     => array(),
            'type'              => '',
            'archived'          => '',
            'deleted'           => '',
            'orderby'           => 'blog_id',
            'order'             => 'ASC',
            'offset'            => '',
            'number'            => '',
            'paged'             => 1,
            'count_total'       => true,
            'search'            => '',
            'search_columns'    => array(),
            'ent_user'            => '',
            'wp_user'            => '',
            'role'           => '',
            'domain'            => '',
        );

        return wp_parse_args( $args, $defaults );
    }

    /**
     * Prepare the query variables.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string|array $query {
     *     Optional. Array or string of Query parameters.
     *
     *     @type int|array    $blog_id             A blog_id or a blog_id aray . Default is empty.
     *     @type boolean      $return_blogs        A boolean saying what object should be returned, if it's true, it returns WP_Site
     *                                             else Ent_Blog_Meta_Model, default false
     *     @type string|array $structure           A string of the structure_id that ent blog meta must match to be
     *                                             included in results. Default empty.
     *     @type array        $structure__in       An array of structure_id. Matched ent blog meta must match at least one
     *                                             of these structure_id. Default empty array.
     *     @type array        $structure__not_in   An array of structure_id to exclude. Ent blog meta matching one or more
     *                                             of these structure_id will not be included in results. Default empty array.
     *     @type string       $relation            Designates type of relation in where when there is multiple conditions.
     *                                             Accepts 'AND', 'OR'. Default 'AND'.
     *     @type string|array $group               A group_id that ent blog meta must match to be included in results.
     *                                             Default empty.
     *     @type array        $group__in           An array of group_id. Matched ent blog meta must have at least one of these
     *                                             group_id. Default empty array.
     *     @type array        $group__not_in       An array of group_id to exclude. Ent blog meta matching one or more
     *                                             of these group_id will not be included in results. Default empty array.
     *     @type string|array $type                A string containing the type of blogs to show. Possibles values are
     *                                             ETB, CLS, GRP, GPL and ENV. Default empty.
     *     @type string|array $orderby             Field(s) to sort the retrieved results by. May be a single value,
     *                                             an array of values, or a multi-dimensional array with fields as
     *                                             keys and orders ('ASC' or 'DESC') as values. Accepted values are
     *                                             'id', 'blog_id', 'structure_id', 'group_id', 'type', 'site_id',
     *                                             'domain', 'path', 'registered', 'last_updated' , 'public', 'archived'
     *                                             'mature', 'spam', 'deleted', 'lang_id' Default 'id'.
     *     @type string       $order               Designates ascending or descending order of results. Order values
     *                                             passed as part of an `$orderby` array take precedence over this
     *                                             parameter. Accepts 'ASC', 'DESC'. Default 'ASC'.
     *     @type int          $offset              Number of results to offset in retrieved results. Can be used in
     *                                             conjunction with pagination. Default 0.
     *     @type int          $number              Number of results to limit the query for. Can be used in
     *                                             conjunction with pagination. Value -1 (all) is supported, but
     *                                             should be used with caution on larger sites.
     *                                             Default empty (all results).
     *     @type int          $paged               When used with number, defines the page of results to return.
     *                                             Default 1.
     *     @type bool         $count_total         Whether to count the total number of results found. If pagination
     *                                             is not needed, setting this to false can improve performance.
     *                                             Default true.
     *     @type string       $search              Search term(s) to retrieve matching sites for. Default empty.
     *     @type array        $search_columns      Array of column names to be searched. Accepts 'domain' and 'path'.
     *     @type mixed        $ent_user            Ent user object used to add structure and group constraints however
     *                                             contrary to using the structure* or group* individually, it makes
     *                                             the query generated is a parenthesised OR
     *                                             Default empty
     * }
     */
    public function prepare_query( $query = array() ) {
        global $wpdb;

        if ( empty( $this->query_vars ) || ! empty( $query ) ) {
            $this->query_limit = null;
            $this->query_vars = $this->fill_query_vars( $query );
        }

        $qv =& $this->query_vars;
        $qv =  $this->fill_query_vars( $qv );

        $meta_table = $wpdb->base_prefix . Ent_Blog_Meta_Query::$table_name;

        $this->query_fields = $meta_table .".*";
        if( isset( $qv['return_blogs'] ) && $qv['return_blogs'] )
            $this->query_fields .= ", $wpdb->blogs.*";
        if ( isset( $qv['count_total'] ) && $qv['count_total'] )
            $this->query_fields = 'SQL_CALC_FOUND_ROWS ' . $this->query_fields;

        $this->query_from = "FROM " . $meta_table;
        if( isset( $qv['return_blogs'] ) && $qv['return_blogs'] )
            $this->query_from .= " JOIN $wpdb->blogs ON $wpdb->blogs.blog_id = $meta_table.blog_id" ;

        $this->query_where = "WHERE";
        $relation = '';

        // Falsey search strings are ignored.
        if ( strlen( $qv['search'] ) ) {
            $search_columns = array();

            if ( $this->query_vars['search_columns'] ) {
                $search_columns = array_intersect( $qv['search_columns'], array( 'domain', "blog_id", 'structure_id', 'group_id', 'name' ) );
            }

            if ( ! $search_columns ) {
                $search_columns = array( 'domain', "blog_id", 'structure_id', 'group_id', 'name' );
            }

            // Since there's 2 blog_id column, we need to put the table name or else SQL throws a error
            if( in_array( 'blog_id', $search_columns ) ) {
                $search_columns = array_replace( $search_columns, array( '1' => "$meta_table.blog_id") );
            }

            if( !isset( $qv['return_blogs'] ) || $qv['return_blogs'] == false )
                array_splice( $search_columns, 0 ,1 );


            $this->query_where .= $this->get_search_sql( $qv['search'], $search_columns );
            $relation = $qv['relation'];
        }

        if ( '' !== $qv['blog_id'] ) {

            $blog_ids = esc_sql( $qv['blog_id'] );
            if ( is_array( $qv['blog_id'] ) ) {
                $blog_ids = implode( ', ', $blog_ids );
            }
            $this->query_where .= $wpdb->prepare( " $relation $meta_table.blog_id IN ( {$blog_ids} )", array() );
            $relation = $qv['relation'];
        }

        // structure
        if ( '' !== $qv['structure_id']) {
            $this->query_where .= $wpdb->prepare( " $relation structure_id = %s", $qv['structure_id'] );
            $relation = $qv['relation'];
        }

        if ( ! empty( $qv['structure__in'] ) ) {
            $sanitized_structure__in = array_map( 'esc_sql', $qv['structure__in'] );
            $structure__in = implode( "','", $sanitized_structure__in );
            $this->query_where .= " $relation structure_id IN ( '$structure__in' )";
            $relation = $qv['relation'];
        }

        if ( ! empty( $qv['structure__not_in'] ) ) {
            $sanitized_structure__not_in = array_map( 'esc_sql', $qv['structure__not_in'] );
            $structure__not_in = implode( "','", $sanitized_structure__not_in );
            $this->query_where .= " $relation structure_id NOT IN ( '$structure__not_in' )";
            $relation = $qv['relation'];
        }

        // type
        if ( '' !== $qv['type']) {
            $this->query_where .= $wpdb->prepare( " $relation type = %s", $qv['type'] );
            $relation = $qv['relation'];
        }

        // archived
        if ( '' !== $qv['archived']) {
            $this->query_where .= $wpdb->prepare( " $relation archived = %s", $qv['archived'] );
            $relation = $qv['relation'];
        }

        // deleted
        if ( '' !== $qv['deleted']) {
            $this->query_where .= $wpdb->prepare( " $relation deleted = %d", $qv['deleted'] );
            $relation = $qv['relation'];
        }

        // group_id
        if ( '' !== $qv['group_id'] ) {
            $this->query_where .= $wpdb->prepare( " $relation group_id = %d", $qv['group_id'] );
            $relation = $qv['relation'];
        }

        if ( ! empty( $qv['group__in'] ) ) {
            $sanitized_group__in = array_map( 'esc_sql', $qv['group__in'] );
            $group__in = implode( ",", $sanitized_group__in );
            $this->query_where .= " $relation group_id IN ( $group__in )";
            $relation = $qv['relation'];
        }

        if ( ! empty( $qv['group__not_in'] ) ) {
            $sanitized_group__not_in = array_map( 'esc_sql', $qv['group__not_in'] );
            $group__not_in = implode( ",", $sanitized_group__not_in );
            $this->query_where .= " $relation group_id NOT IN ( $group__not_in )";
            $relation = $qv['relation'];
        }

        // ent_user
        if ( ! empty( $qv['ent_user'] ) ) {
            $structures = array_map( function ( $profile ) { return $profile->structure_id; }, $qv['ent_user']->profiles );
            $structures = array_map( function ( $s ) { return "'" . esc_sql($s) . "'"; }, array_unique( $structures ) );
            $structures = implode( ', ', $structures );

            $groups = array_map( function ( $group ) { return $group->group_id; }, $qv['ent_user']->groups );
            $groups = esc_sql( array_unique( $groups ) );
            $groups = implode( ', ', $groups );

            // wp_user
            $keys = get_user_meta( $qv['wp_user']->ID );
            $site_ids = array();
            foreach ( $keys as $key => $value ) {
                if ( 'capabilities' !== substr( $key, -12 ) )
                    continue;
                if ( $wpdb->base_prefix && 0 !== strpos( $key, $wpdb->base_prefix ) )
                    continue;
                $site_id = str_replace( array( $wpdb->base_prefix, '_capabilities' ), '', $key );
                if ( ! is_numeric( $site_id ) )
                    continue;
                // Role
                if( empty( $qv['role'] ) )
                    $site_ids[] = (int) $site_id;
                else {
                    foreach($value as $role) {
                        $unserialized = maybe_unserialize($role);
                        if($role == $unserialized) { continue; }
                        if( array_key_exists( $qv['role'], $unserialized ) )
                            $site_ids[] = (int) $site_id;
                    }
                }
            }
            $site_ids = implode( ', ', $site_ids );
            $sub_relation = "";
            $sub_query = "";

            if( !empty($structures) ) {
                $sub_query .= " $sub_relation structure_id IN ( {$structures} )";
                $sub_relation = "OR";
            }

            if( !empty($groups) ) {
                $sub_query .= " $sub_relation group_id IN ( {$groups} ) ";
                $sub_relation = "OR";
            }

            if( !empty( $site_ids ) ) {
                $sub_query .= " $sub_relation $meta_table.blog_id IN ( {$site_ids} ) ";
                $sub_relation = "OR";
            }
            if( !empty( $sub_query ) ) {
                $this->query_where .= $wpdb->prepare( " $relation ( $sub_query ) ",array() );
                $relation = $qv['relation'];
            }
        }
        if ( ! empty( $qv['domain'] ) && isset( $qv['return_blogs'] ) && $qv['return_blogs']) {
            $this->query_where .= $wpdb->prepare( " $relation domain = %s", $qv['domain'] );
            $relation = $qv['relation'];
        }

        if($this->query_where == 'WHERE') {
            $this->query_where .= ' 1=1';
        }

        // sorting
        $qv['order'] = isset( $qv['order'] ) ? strtoupper( $qv['order'] ) : '';
        $order = $this->parse_order( $qv['order'] );

        if ( empty( $qv['orderby'] ) ) {
            // Default order is by 'blog_id'.
            $ordersby = array( 'blog_id' => $order );
        } elseif ( is_array( $qv['orderby'] ) ) {
            $ordersby = $qv['orderby'];
        } else {
            // 'orderby' values may be a comma- or space-separated list.
            $ordersby = preg_split( '/[,\s]+/', $qv['orderby'] );
        }

        $orderby_array = array();
        foreach ( $ordersby as $_key => $_value ) {
            if ( ! $_value ) {
                continue;
            }

            if ( is_int( $_key ) ) {
                // Integer key means this is a flat array of 'orderby' fields.
                $_orderby = $_value;
                $_order = $order;
            } else {
                // Non-integer key means this the key is the field and the value is ASC/DESC.
                $_orderby = $_key;
                $_order = $_value;
            }

            $parsed = $this->parse_orderby( $_orderby );

            if ( ! $parsed ) {
                continue;
            }

            $orderby_array[] = $parsed . ' ' . $this->parse_order( $_order );
        }

        // If no valid clauses were found, order by blog_id.
        if ( empty( $orderby_array ) ) {
            $orderby_array[] = "$meta_table.blog_id $order";
        }

        $this->query_orderby = 'ORDER BY ' . implode( ', ', $orderby_array );

        // limit
        if ( isset( $qv['number'] ) && $qv['number'] > 0 ) {
            if ( $qv['offset'] ) {
                $this->query_limit = $wpdb->prepare("LIMIT %d, %d", $qv['offset'], $qv['number']);
            } else {
                $this->query_limit = $wpdb->prepare( "LIMIT %d, %d", $qv['number'] * ( $qv['paged'] - 1 ), $qv['number'] );
            }
        }
    }

    /**
     * Execute the query, with the current variables.
     *
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     */
    public function query() {
        global $wpdb;

        $qv =& $this->query_vars;

        $this->request = "SELECT $this->query_fields $this->query_from $this->query_where $this->query_orderby $this->query_limit";

        $this->results = $wpdb->get_results( $this->request );

        if ( isset( $qv['count_total'] ) && $qv['count_total'] )
            $this->total_found = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

        if ( !$this->results )
            return;

        if ( isset( $qv['return_blogs'] ) && $qv['return_blogs'] ) {
            foreach ( $this->results as $key => $wp_blog ) {
                $this->results[ $key ] = new WP_Site( $wp_blog );
            }
            return;
        }

        foreach ( $this->results as $key => $ent_blog_meta ) {
            $this->results[ $key ] = new Ent_Blog_Meta_Model( $ent_blog_meta );
        }

    }

    /**
     * Retrieve query variable.
     *
     *
     * @param string $query_var Query variable key.
     * @return mixed
     */
    public function get( $query_var ) {
        if ( isset( $this->query_vars[$query_var] ) )
            return $this->query_vars[$query_var];

        return null;
    }

    /**
     * Set query variable.
     *
     *
     * @param string $query_var Query variable key.
     * @param mixed $value Query variable value.
     */
    public function set( $query_var, $value ) {
        $this->query_vars[$query_var] = $value;
    }

    /**
     * Return the list of ent_blog_meta.
     *
     *
     * @return array Array of results.
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Return the total number of ent_blog_meta for the current query.
     *
     *
     * @return int Number of total ent_blog_meta.
     */
    public function get_total() {
        return $this->total_found;
    }

 /**
     * Used internally to generate an SQL string for searching across multiple columns.
     *
     * @since 4.6.0
     *
     * @global wpdb  $wpdb WordPress database abstraction object.
     *
     * @param string $string  Search string.
     * @param array  $columns Columns to search.
     * @return string Search SQL.
     */
    protected function get_search_sql( $string, $columns ) {
        global $wpdb;

        if ( false !== strpos( $string, '*' ) ) {
            $like = '%' . implode( '%', array_map( array( $wpdb, 'esc_like' ), explode( '*', $string ) ) ) . '%';
        } else {
            $like = '%' . $wpdb->esc_like( $string ) . '%';
        }

        $searches = array();
        foreach ( $columns as $column ) {
            $searches[] = $wpdb->prepare( "$column LIKE %s", $like );
        }

        return ' (' . implode( ' OR ', $searches ) . ')';
    }

    /**
     * Parse and sanitize 'orderby' keys passed to the ent_blog_meta query.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $orderby Alias for the field to order by.
     * @return string Value to used in the ORDER clause, if `$orderby` is valid.
     */
    protected function parse_orderby( $orderby ) {
        global $wpdb;

        $meta_table = $wpdb->base_prefix . Ent_Blog_Meta_Query::$table_name;
        $_orderby = '';
        if ( in_array( $orderby, array( 'type', "$meta_table.blog_id", 'structure_id', 'group_id', 'name' ) ) ) {
            $_orderby = $orderby;
        } elseif ( 'ID' == $orderby || 'id' == $orderby ) {
            $_orderby = 'id';
        } elseif ( 'blog_id' == $orderby ) {
            $_orderby = "$meta_table.$orderby";
        } elseif ( isset( $this->query_vars['return_blogs'] ) && $this->query_vars['return_blogs']
            && in_array(  $orderby, array( 'domain', 'registered', 'last_updated' ) ) ) {
                $_orderby = $orderby;
        }

        return $_orderby;
    }


    /**
     * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
     *
     *
     * @param string $order The 'order' query variable.
     * @return string The sanitized 'order' query variable.
     */
    protected function parse_order( $order ) {
        if ( ! is_string( $order ) || empty( $order ) ) {
            return 'DESC';
        }

        if ( 'ASC' === strtoupper( $order ) ) {
            return 'ASC';
        } else {
            return 'DESC';
        }
    }
}