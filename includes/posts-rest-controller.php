<?php
class Posts_Controller extends Laclasse_Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->rest_base = 'posts';
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        // GET /posts
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_posts'),
                'permission_callback' => array($this, 'get_posts_permissions_check'),
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
    public function get_posts($request)
    {
        $query_params = $request->get_query_params();

        if (array_key_exists('date<', $query_params)) {
            $after = DateTime::createFromFormat("Y-m-d\TG:i:s.uP", $query_params['date<']);
            if ($after == false) {
                return new WP_REST_Response('Wrong date< format', 400);
            }

            $query_params['date_query'][] = array(
                'before' => $query_params['date<'],
            );
            unset($query_params['date<']);
        }

        if (array_key_exists('date>', $query_params)) {
            $before = DateTime::createFromFormat("Y-m-d\TG:i:s.uP", $query_params['date>']);
            if ($before == false) {
                return new WP_REST_Response('Wrong date> format', 400);
            }
            $query_params['date_query'][] = array(
                'after' => $query_params['date>'],
            );
            unset($query_params['date>']);
        }

        if (array_key_exists('limit', $query_params)) {
            $filters['limit'] = $query_params['limit'];
            unset($query_params['limit']);
        }

        if (array_key_exists('page', $query_params)) {
            $filters['page'] = $query_params['page'];
            unset($query_params['page']);
        } else {
            $filters['page'] = 1;
        }

        if (array_key_exists('sort_dir', $query_params)
            && (strcasecmp($query_params['sort_dir'], 'ASC') || strcasecmp($query_params['sort_dir'], 'DESC'))) {
            $filters['sort_dir'] = $query_params['sort_dir'];
            unset($query_params['sort_dir']);
        } else {
            $filters['sort_dir'] = 'DESC';
        }

        if (array_key_exists('sort_col', $query_params)) {
            $avaliable_order_cols = ['ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content',
                'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password',
                'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered',
                'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count', 'filter',
            ];
            if (in_array($query_params['sort_col'], $avaliable_order_cols)) {
                $filters['sort_col'] = $query_params['sort_col'];
            }
            unset($query_params['sort_col']);
        } else {
            $filters['sort_col'] = 'post_date';
        }
        $default_params = array(
            'has_password' => false,
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
        );

        $params = array_merge($default_params, $query_params);
        $original_blog_id = get_current_blog_id();
        $data = [];

        if ($this->wp_user) {
            $user_blogs = get_blogs_of_user($this->wp_user->ID);
            foreach ($user_blogs as $user_blog) {
                switch_to_blog($user_blog->userblog_id);
                $the_query = new WP_Query($params);
                $found_posts = $the_query->get_posts();
                foreach ($found_posts as $post) {
                    $data[] = $this->prepare_post_for_response($post, $request, $user_blog);
                }
            }
        } else {
            // wp_user doesn't exist, we look for all blogs
            $blogs = get_blogs();
            foreach ($blogs as $blog) {
                if (!is_forced_blog($blog, $this->ent_user)) {
                    continue;
                }

                switch_to_blog($blog->id);
                $the_query = new WP_Query($params);
                $found_posts = $the_query->get_posts();
                foreach ($found_posts as $post) {
                    $data[] = $this->prepare_post_for_response($post, $request, $blog);
                }
            }
        }
        switch_to_blog($original_blog_id);

        //post_fetch filter processing ie paging,reordering, etc...
        usort($data, function ($left, $right) use ($filters) {
            $cmp = strcmp($left->{$filters['sort_col']}, $right->{$filters['sort_col']});
            return strcasecmp($filters['sort_dir'], 'DESC') ? $cmp : $cmp * -1;
        });
        if (array_key_exists('limit', $filters)) {
            $offset = ($filters['page'] - 1) * $filters['limit'];
            $data = (object) [
                'total' => count($data),
                'limit' => intval($filters['limit']),
                'page' => $filters['page'],
                'data' => array_splice($data, $offset, $filters['limit']),
            ];
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a given request has access to get post
     * /!\ If seen_by is present in URL parameters
     * it can change the wp_user or ent_user in order to find what would be seen by seen_by's user
     *
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_posts_permissions_check($request)
    {
        if ($this->permission_checked) {
            return true;
        }

        $this->permission_checked = true;
        if (!$this->is_user_logged_in($request)) {
            return new WP_Error('unauthorized', null, array('status' => 401));
        }

        $query_params = $request->get_query_params();

        if (array_key_exists('seen_by', $query_params)) {
            if ($query_params['seen_by'] != $this->ent_user->id) {
                $seenBy = get_ent_user($query_params['seen_by']);

                if ($seenBy == null) {
                    return false;
                }

                if ($this->ent_user->super_admin) {
                    $this->wp_user = get_wp_user_from_ent_user($seenBy);
                    if ($this->wp_user == null) {
                        $this->ent_user = $seenBy;
                    }
                } else {
                    foreach ($seenBy->profiles as $profile) {
                        if (has_profile($this->ent_user, $profile->structure_id, ['DIR', 'ADM'])) {
                            $this->wp_user = get_wp_user_from_ent_user($seenBy);
                            if ($this->wp_user == null) {
                                $this->ent_user = $seenBy;
                            }
                            return true;
                        }
                    }
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Prepare the post for the REST response
     *
     * @param WP_User $post WordPress representation of the post.
     * @param WP_REST_Request $request Request object.
     * @param stdClass $blog A blog
     * @return mixed
     */
    public function prepare_post_for_response($post, $request, $blog)
    {
        $result = new stdClass();
        if (isset($blog->id)) {
            $result->blog_id = $blog->id;
            $result->blog_name = $blog->name;
            $result->blog_domain = $blog->domain;
        } else {
            $result->blog_id = $blog->userblog_id;
            $result->blog_name = $blog->blogname;
            $result->blog_domain = $blog->domain;
        }
        $keys = array(
            'ID', 'post_date','post_title','post_status','guid','post_type',
            'comment_status','ping_status','post_modified',
        );
        foreach($keys as $value) {
            $result->$value = $post->$value;
        }

        if( isset($post->post_date) ) {
            $datetime = new DateTime($post->post_date);
            $result->post_date = $datetime->format(DateTime::ATOM);
        }

        if( isset($post->post_modified) ) {
            $datetime = new DateTime($post->post_modified);
            $result->post_modified = $datetime->format(DateTime::ATOM);
        }

        $result->post_link = get_permalink($post->ID);
        // search for audio MP3 shortcode if any
        $pattern = get_shortcode_regex();
        if (preg_match_all('/'. $pattern .'/s', $post->post_content, $matches)
            && array_key_exists(2, $matches)
            && in_array('audio', $matches[2])
            && array_key_exists(3, $matches)) {
            if (preg_match('/mp3="([^\"]+)"/', $matches[3][0], $mp3_matches)) {
                $result->post_audio_mp3 = $mp3_matches[1];
            }
        }
        $result->post_text = html_entity_decode(strip_shortcodes(wp_strip_all_tags($post->post_content)));
        if(has_post_thumbnail($post->ID)) {
            $result->post_thumbnail = wp_get_attachment_image_url(get_post_thumbnail_id($post->ID),'medium');
        }
        if(!empty($post->post_content)) {
            $dom = new DOMDocument();
            // Use internal errors because loadHTML doesn't support fully HTML5 tags or syntax
            // Nothing is done there so everything is discarded here
            $old = libxml_use_internal_errors(true);
            // By default loadHTML uses ISO-8859-1 so we fix that using XML Declaration
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $post->post_content);
            libxml_use_internal_errors($old);
            // Finds images based on <img> tags, this prioritize images with a specified height and width
            // if none were found it takes the first image with unspecified height and/or width
            foreach( $dom->getElementsByTagName( "img" ) as $image ) {
                if($image->hasAttribute('width') && $image->hasAttribute('height')
                    && $image->getAttribute('width') >= 100 && $image->getAttribute('height') >= 100) {
                    $result->post_image = $image->getAttribute("src");
                    break;
                } else if(!isset($result->post_image)){
                    $result->post_image = $image->getAttribute("src");
                }
            }
            // Finds videos based on video shortcode
            preg_match( '/'.get_shortcode_regex( array('video') ).'/', $post->post_content, $video);
            if($video != false) {
                preg_match('/(?:src|mp4|m4v|webm|ogv|wmv|flv)="(.*)"/', $video[3], $video_src );
                $result->post_video = $video_src ? $video_src[1] : null;
            }
            // Find gallery based on gallery short_code if no image was found
            if( !isset($result->post_image)) {
                $gallery = get_post_gallery_images($post->ID);
                if(isset($gallery) && count($gallery) > 0)
                    $result->post_image = $gallery[0];
            }
        }
        return $result;
    }

}
