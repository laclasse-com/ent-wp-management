<?php
class Ent_Blog_Meta_Model {


    /**
     * Data container.
     *
     * @var object
     */
    public $data;

     /**
     * The ent blog meta ID.
     *
     * @var int
     */
    public $ID = 0;

    /**
     * Constructor.
     *
     * Retrieves the blog metadata and passes it to Ent_Blog_Meta_Model::init().
     *
     * @since 2.0.0
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param int|string|stdClass $id Ent_Blog_Meta's ID, or a Ent_Blog_Meta object from the DB.
     */
    public function __construct( $id = 0) {
        if ( is_object( $id ) ) {
            $this->init( $id );
            return;
        }

        if ( ! empty( $id ) && ! is_numeric( $id ) ) {
            $id = 0;
        }

        if ( $id ) {
            $data = self::get_data_by( 'id', $id );
        }

        if ( $data ) {
            $this->init( $data );
        } else {
            $this->data = new stdClass;
        }
    }

    /**
     * Sets up object properties
     *
     * @param object $data    Ent_Blog_Meta DB row object.
     */
    public function init( $data ) {
        $this->data = $data;
        $this->ID = (int) $data->id;
    }

    /**
     * Return only the main fields
     *
     * @static
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $field The field to query against: 'id', 'ID', 'blog_id', 'structure_id', 'group_id','type'.
     * @param string|int $value The field value
     * @return object|false Raw ent_meta object
     */
    public static function get_data_by( $field, $value ) {
        global $wpdb;

        // 'ID' is an alias of 'id'.
        if ( 'ID' === $field ) {
            $field = 'id';
        }

        if ( 'id' == $field ) {
            // Make sure the value is numeric to avoid casting objects, for example,
            // to int 1.
            if ( ! is_numeric( $value ) )
                return false;
            $value = intval( $value );
            if ( $value < 1 )
                return false;
        } else {
            $value = trim( $value );
        }

        if ( !$value )
            return false;

        switch ( $field ) {
            case 'id':
            case 'blog_id':
            case 'structure_id':
            case 'type':
            case 'group_id':
                $db_field = $field;
                break;
            default:
                return false;
        }

        if ( !$ent_meta = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . $wpdb->base_prefix . Ent_Blog_Meta_Query::$table_name ." WHERE $db_field = %s", $value
        ) ) )
            return false;

        return $ent_meta;
    }

    public function __get( $key ) {
        if ( isset( $this->data->$key ) ) {
            $value = $this->data->$key;
        }

        return $value;
    }

    public function __set( $key, $value ) {
        $this->data->$key = $value;
    }

    /**
     * Return an array representation.
     *
     * @return array Array representation.
     */
    public function to_array() {
        return get_object_vars( $this->data );
    }

    /**
     * Converts a Ent_Blog_Meta object to own Blog object
     * not unlike blog_data in api.php
     *
     * @return void
     */
    function toBlogData() {
        $result = new stdClass;
        if( empty( $this->data ) ) { return null;}

        $result->id = $this->data->blog_id;
        $result->group_id = $this->data->group_id;
        $result->structure_id = $this->data->structure_id;
        $result->type = $this->data->type;

        return $result;
    }

    /**
     * Insert a ent_blog_meta into the database.
     *
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param array|object|Ent_Blog_Meta_Model $metadata {
     *     An array, object, or WP_User object of meta data arguments.
     *
     *     @type int         $ID                   The Ent_Blog_Meta_Model ID
     *     @type int         $blog_id              The id of the blog it refers to
     *     @type int         $group_id             The id of the group it's attached to
     *     @type string      $structure_id         The id of the structure it's attached to
     *     @type string      $type                 The type of blog
     * }
     * @return int|WP_Error The newly created blog meta's ID or a WP_Error object if it could not
     *                      be created.
    */

    static function wp_insert_or_update( $metadata ) {
        global $wpdb;

        if ( $metadata instanceof stdClass ) {
            $metadata = get_object_vars( $metadata );
        } elseif ( $metadata instanceof Ent_Blog_Meta_Model ) {
            $metadata = $metadata->to_array();
        }

        // Are we updating or creating?
        if ( ! empty( $metadata['id'] ) ) {
            $ID = (int) $metadata['id'];
            $update = true;
            $old_user_data = Ent_Blog_Meta_Model::get_data_by('id', $ID );
            if ( ! $old_user_data ) {
                return new WP_Error( 'invalid_ent_blog_meta_id', __( 'Invalid ent blog meta ID.' ) );
            }
        } else {
            $update = false;
        }


        $sanitized_structure_id = sanitize_text_field( $metadata['structure_id'], true );
        $structure_id = trim( $sanitized_structure_id );
        if ( empty( $structure_id ) ) {
            $structure_id = null;
        } elseif ( mb_strlen( $structure_id ) > 8 ) {
            return new WP_Error( 'structure_id_too_long', __( 'structure_id may not be longer than 8 characters.' ) );
        }


        if( is_numeric( $metadata['blog_id'] ) )
            $blog_id = $metadata['blog_id'];

        if( isset( $metadata['group_id'] ) && is_numeric( $metadata['group_id'] ) )
            $group_id = $metadata['group_id'];
        else
            $group_id = null;

        $sanitized_type = sanitize_text_field( $metadata['type'], true );
        $type = trim( $sanitized_type );
        if ( empty( $type ) ) {
            return new WP_Error( 'invalid_ent_type', __( 'Invalid ent blog type.' ) );
        }

        $sanitized_name = sanitize_text_field( $metadata['name'], true );
        $name = trim( $sanitized_name );
        if ( empty( $name ) ) {
            return new WP_Error( 'invalid_name', __( 'Invalid name.' ) );
        }

        $compacted = compact( 'structure_id', 'group_id', 'type', 'blog_id', 'name' );
        $data = wp_unslash( $compacted );

        $table_name = $wpdb->base_prefix . Ent_Blog_Meta_Query::$table_name;
        if ( $update ) {
            $wpdb->update( $table_name, $data, compact( 'ID' ) );
            $ent_blog_meta_id = (int) $ID;
        } else {
            $wpdb->insert( $table_name, $data );
            $ent_blog_meta_id = (int) $wpdb->insert_id;
        }

        return $ent_blog_meta_id;
    }
}