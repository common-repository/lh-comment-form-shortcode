<?php
/**
 * Plugin Name: LH Comment Form Shortcode
 * Plugin URI: https://lhero.org/portfolio/lh-comment-form-shortcode/
 * Description: Unbundle comments from their post by shortcodes for comment forms and listings
 * Author: Peter Shaw
 * Author URI: https://shawfactor.com
 * Text Domain: lh_comment_form_shortcode
 * Domain Path: /languages
 * Version: 1.01
*/

if (!class_exists('LH_Comment_form_shortcode_plugin')) {


class LH_Comment_form_shortcode_plugin {

    private static $instance;
    var $has_run = false;

    static function return_plugin_namespace(){
    
        return 'lh_comment_form_shortcode';
    
    }

    public function close_comments($comment_status, $post_id){
        
        return 'closed'; 
        
    }

    public function flip_comments( $comments , $post_id ) {
        
        return array_reverse($comments);
    }

    public function buffer_start(){
        
        ob_start();
        
    }

    public function buffer_end(){
        
        $page = ob_get_contents();
        ob_end_clean();
        
    }

    public function comment_form_output($atts, $content = '') {
        
        // define attributes and their defaults
        extract(
            shortcode_atts(
                array (
                    'post_id' => false
                ),
                $atts
            )
        );
        
        if ( empty($post_id) ) {
            
            $post_id = get_the_ID();
            
        } else {
            
            $post_id = intval($post_id);
            
        }
        
        $args = array();
        
    
        
        ob_start();
        comment_form($args, $post_id);
        $cform = ob_get_contents();
        ob_end_clean();
        
        //add_filter('comments_open', array($this, 'close_comments'), 10, 2 );
        add_filter( 'comments_array' , array($this, 'flip_comments'), 10, 2);
    
        if (!empty($this->has_run)){
    
            add_filter('comment_form_before' , array($this, 'buffer_start'), 10);
            add_filter('comment_form_after' , array($this, 'buffer_end'), 10);
    
        }
                
        return $cform;   
    
    }

    public function register_inline_styles(){
        
      
            
        wp_register_style( self::return_plugin_namespace().'-inline', false );
        wp_enqueue_style( self::return_plugin_namespace().'-inline' );
        
        $string = 'ol.'.self::return_plugin_namespace().'-list li {
        
        list-style-type: none;
        list-style: none;
            
        }';
        wp_add_inline_style( self::return_plugin_namespace().'-inline', $string );
    
    }
    

    public function comment_list_output($atts, $content = ''){
    
        global $wp_query, $post, $wpdb, $id, $comment, $user_login, $user_identity, $overridden_cpage;
    
        // define attributes and their defaults
        extract(
            shortcode_atts(
                array (
                    'separate_comments' => false,
                    'post_id' => false,
                ),
                $atts
            )
        );
        
        if (empty($post_id) && !empty($post->ID)){
            
            $post_id = $post->ID;
            
        }
        
        if (empty($post_id)){
            
            return;
            
        }
 
 
        if ( empty( $file ) ) {
            
            $file = '/comments.php';
            
        }
 
        $req = get_option( 'require_name_email' );
 
        /*
         * Comment author information fetched from the comment cookies.
         */
         
        $commenter = wp_get_current_commenter();
     
        /*
         * The name of the current comment author escaped for use in attributes.
         * Escaped by sanitize_comment_cookies().
         */
         
        $comment_author = $commenter['comment_author'];
     
        /*
         * The email address of the current comment author escaped for use in attributes.
         * Escaped by sanitize_comment_cookies().
         */
         
        $comment_author_email = $commenter['comment_author_email'];
     
        /*
         * The URL of the current comment author escaped for use in attributes.
         */
         
        $comment_author_url = esc_url( $commenter['comment_author_url'] );
 
        $comment_args = array(
            'orderby'                   => 'comment_date_gmt',
            'order'                     => 'ASC',
            'status'                    => 'approve',
            'post_id'                   => $post_id,
            'no_found_rows'             => false,
            'update_comment_meta_cache' => false, // We lazy-load comment meta for performance.
        );
 
        if ( get_option( 'thread_comments' ) ) {
            
            $comment_args['hierarchical'] = 'threaded';
            
        } else {
            
            $comment_args['hierarchical'] = false;
        
        }
 
        if ( is_user_logged_in() ) {
            
            $comment_args['include_unapproved'] = array( get_current_user_id() );
            
        } else {
            
            $unapproved_email = wp_get_unapproved_comment_author_email();
     
            if ( $unapproved_email ) {
                
                $comment_args['include_unapproved'] = array( $unapproved_email );
                
            }
        }
 
        $per_page = 0;
        if ( get_option( 'page_comments' ) ) {
            $per_page = (int) get_query_var( 'comments_per_page' );
            if ( 0 === $per_page ) {
                $per_page = (int) get_option( 'comments_per_page' );
            }
     
            $comment_args['number'] = $per_page;
            $page                   = (int) get_query_var( 'cpage' );
     
            if ( $page ) {
                $comment_args['offset'] = ( $page - 1 ) * $per_page;
            } elseif ( 'oldest' === get_option( 'default_comments_page' ) ) {
                $comment_args['offset'] = 0;
            } else {
                // If fetching the first page of 'newest', we need a top-level comment count.
                $top_level_query = new WP_Comment_Query();
                $top_level_args  = array(
                    'count'   => true,
                    'orderby' => false,
                    'post_id' => $post->ID,
                    'status'  => 'approve',
                );
     
                if ( $comment_args['hierarchical'] ) {
                    $top_level_args['parent'] = 0;
                }
     
                if ( isset( $comment_args['include_unapproved'] ) ) {
                    $top_level_args['include_unapproved'] = $comment_args['include_unapproved'];
                }
     
                /**
                 * Filters the arguments used in the top level comments query.
                 *
                 * @since 5.6.0
                 *
                 * @see WP_Comment_Query::__construct()
                 *
                 * @param array $top_level_args {
                 *     The top level query arguments for the comments template.
                 *
                 *     @type bool         $count   Whether to return a comment count.
                 *     @type string|array $orderby The field(s) to order by.
                 *     @type int          $post_id The post ID.
                 *     @type string|array $status  The comment status to limit results by.
                 * }
                 */
                $top_level_args = apply_filters( 'comments_template_top_level_query_args', $top_level_args );
     
                $top_level_count = $top_level_query->query( $top_level_args );
     
                $comment_args['offset'] = ( ceil( $top_level_count / $per_page ) - 1 ) * $per_page;
            }
        }
 
        /**
         * Filters the arguments used to query comments in comments_template().
         *
         * @since 4.5.0
         *
         * @see WP_Comment_Query::__construct()
         *
         * @param array $comment_args {
         *     Array of WP_Comment_Query arguments.
         *
         *     @type string|array $orderby                   Field(s) to order by.
         *     @type string       $order                     Order of results. Accepts 'ASC' or 'DESC'.
         *     @type string       $status                    Comment status.
         *     @type array        $include_unapproved        Array of IDs or email addresses whose unapproved comments
         *                                                   will be included in results.
         *     @type int          $post_id                   ID of the post.
         *     @type bool         $no_found_rows             Whether to refrain from querying for found rows.
         *     @type bool         $update_comment_meta_cache Whether to prime cache for comment meta.
         *     @type bool|string  $hierarchical              Whether to query for comments hierarchically.
         *     @type int          $offset                    Comment offset.
         *     @type int          $number                    Number of comments to fetch.
         * }
         */
        $comment_args = apply_filters( 'comments_template_query_args', $comment_args );
     
        $comment_query = new WP_Comment_Query( $comment_args );
        $_comments     = $comment_query->comments;
     
        // Trees must be flattened before they're passed to the walker.
        if ( $comment_args['hierarchical'] ) {
            $comments_flat = array();
            foreach ( $_comments as $_comment ) {
                $comments_flat[]  = $_comment;
                $comment_children = $_comment->get_children(
                    array(
                        'format'  => 'flat',
                        'status'  => $comment_args['status'],
                        'orderby' => $comment_args['orderby'],
                    )
                );
     
                foreach ( $comment_children as $comment_child ) {
                    $comments_flat[] = $comment_child;
                }
            }
        } else {
            
            $comments_flat = $_comments;
            
        }
 
        /**
         * Filters the comments array.
         *
         * @since 2.1.0
         *
         * @param array $comments Array of comments supplied to the comments template.
         * @param int   $post_ID  Post ID.
         */
        $wp_query->comments = apply_filters( 'comments_array', $comments_flat, $post->ID );
     
        $comments                        = &$wp_query->comments;
        $wp_query->comment_count         = count( $wp_query->comments );
        $wp_query->max_num_comment_pages = $comment_query->max_num_pages;
 
        if ( $separate_comments ) {
            
            $wp_query->comments_by_type = separate_comments( $comments );
            $comments_by_type           = &$wp_query->comments_by_type;
            
        } else {
            
            $wp_query->comments_by_type = array();
            
        }
 
        $overridden_cpage = false;
     
        if ( '' == get_query_var( 'cpage' ) && $wp_query->max_num_comment_pages > 1 ) {
            
            set_query_var( 'cpage', 'newest' === get_option( 'default_comments_page' ) ? get_comment_pages_count() : 1 );
            $overridden_cpage = true;
            
        }
     
        if ( ! defined( 'COMMENTS_TEMPLATE' ) ) {
            
            define( 'COMMENTS_TEMPLATE', true );
            
        }
    
    
    
        ob_start();
    
        $return_string = '';
    
        if ( have_comments() ){ ?>
        	<h3 id="comments">
        		<?php
        		if ( 1 == get_comments_number() ) {
        			printf(
        				/* translators: %s: Post title. */
        				__( 'One response to %s' ),
        				'&#8220;' . get_the_title() . '&#8221;'
        			);
        		} else {
        			printf(
        				/* translators: 1: Number of comments, 2: Post title. */
        				_n( '%1$s response to %2$s', '%1$s responses to %2$s', get_comments_number() ),
        				number_format_i18n( get_comments_number() ),
        				'&#8220;' . get_the_title() . '&#8221;'
        			);
        		}
        		?>
        	</h3>
        
        	<div class="navigation">
        		<div class="alignleft"><?php previous_comments_link(); ?></div>
        		<div class="alignright"><?php next_comments_link(); ?></div>
        	</div>
        
        	<ol class="commentlist <?php echo self::return_plugin_namespace(); ?>-list">
        	<?php wp_list_comments(); ?>
        	</ol>
            
        <?php }    

        $return_string .= ob_get_contents();
        ob_end_clean();
    
        return $return_string;
    
    }

    public function register_shortcodes(){

        //the comment form shortcode
        add_shortcode(self::return_plugin_namespace().'_form', array($this,'comment_form_output'));
        
        //the comment listing shortcode
        add_shortcode(self::return_plugin_namespace().'_list', array($this,'comment_list_output'));
    
    
    }

    public function add_wp_body_open_hooks(){
        
        $this->has_run = true;
        
    }
    
    
    public function plugin_init(){
        
        //load translations
        load_plugin_textdomain( self::return_plugin_namespace(), false, basename( dirname( __FILE__ ) ) . '/languages' );

        //add the shortcodes        
        add_action('init', array($this,'register_shortcodes')); 

        //add inline styles to reset the commenst list
        add_action( 'wp_print_styles', array($this, 'register_inline_styles'), 10 );

        //add others on body open so that it only runs when needed
        add_action( 'wp_body_open', array($this,'add_wp_body_open_hooks'));
    
    }


    /**
     * Gets an instance of our plugin.
     *
     * using the singleton pattern
     */
     
    public static function get_instance(){
        
        if (null === self::$instance) {
            
            self::$instance = new self();
            
        }
 
        return self::$instance;
        
    }
    

    public function __construct() {
        
    	 //run our hooks on plugins loaded to as we may need checks       
        add_action( 'plugins_loaded', array($this,'plugin_init'));
    
    }
    
}

$lh_comment_form_shortcode_instance = LH_Comment_form_shortcode_plugin::get_instance();



}

?>