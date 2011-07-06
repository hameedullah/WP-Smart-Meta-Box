<?php

class SmartMetaBox {

    protected $prefix;
    protected $id;
    protected $title;
    protected $pages;
    protected $post_ids;
    protected $post_slugs;
    protected $callback;
    protected $callback_args;
    protected $context;
    protected $priority;
    protected $fields;


    /**
    * default_properties 
    * 
    * NOTE: where prossible use same defaults as WordPress, so not to confuse user.
    *
    * @var array
    * @access protected
    */
    protected $default_properties = array(
        'prefix' => 'wp_smb_',
        'id' => null,
        'title' => 'WP Smart Meta Box',
        'pages' => array( 'all' ),
        'post_ids' => array(),
        'post_slugs' => array(),
        'callback' => '',
        'callback_args' => null,
		'save_callback' => '',
        'context' => 'advanced',
        'priority' => 'default'
    );

    private $allowed_contexts = array( 'normal', 'advanced', 'side' );
    private $allowed_priorities = array( 'high', 'core', 'default', 'low' );

    public static $meta_box_count = 1;

    function __construct( $meta_box=null ) {
        $this->properties = $meta_box;
        self::$meta_box_count += 1;
        $this->instances = 0;
        $this->setup_properties();
        $this->setup_hooks();
    }

    function setup_properties() {
        $properties = wp_parse_args( $this->properties, $this->default_properties );
        $properties = $this->sanitize_properties( $properties );
        foreach ( $properties as $property => $value ) {
            $this->{$property} = $value;
        }
    }

    function sanitize_properties( $properties ) {
        if ( empty( $properties['prefix'] ) || !is_string( $properties['prefix'] ) ) {
            $properties['prefix'] = $this->default_properties['prefix'];
        }

        if ( empty( $properties['id'] ) || !is_string( $properties['id'] ) ) {
            $properties['id'] = $this->default_properties['prefix'] . self::$meta_box_count;
        }

        if ( empty( $properties['title'] ) || !is_string( $properties['title'] ) ) {
            $properties['title'] = $this->default_properties['title'] . self::$meta_box_count;
        }

        if ( !is_array( $properties['pages'] ) ) {
            $properties['pages'] = explode( ",", $properties['pages'] );
        }

        $properites['pages'] = array_map( 'trim', $properties['pages'] );

        if ( in_array( 'all', $properties['pages'] ) ) {
            /* TODO: add support for all registered custom post types */
            $properties['pages'] = array( 'post', 'page', 'link' );
        }
        
        /* TODO: More input type checks, like single integer id, or false */
        if ( !is_array( $properties['post_ids'] ) ) {
            $properties['post_ids'] = explode( ",", $properties['post_ids'] );
        }

        $properites['post_ids'] = array_map( 'trim', $properties['post_ids'] );

        if ( !in_array( $properties['context'], $this->allowed_contexts ) )
            $properties['context'] = $this->default_properties['context'];

        if ( !in_array( $properties['priority'], $this->allowed_priorities ) )
            $properties['priority'] = $this->default_properties['priority'];

        return $properties;
    }

    function setup_hooks() {
        add_action( 'add_meta_boxes', array( $this, 'add_smart_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_smart_meta_box_data' ), 10, 2 );
    }

    function add_smart_meta_box() {
        global $post;
        if ( !$post ) return;
        if ( $this->post_ids ) {
            if ( !in_array( $post->ID, $this->post_ids ) )
                return false;
        }

        if ( $this->post_slugs ) {
            if ( $post->post_name != $this->post_slugs ) return false;
        }

        foreach( $this->pages as $page ) {
            $this->instances += 1;
            add_meta_box(
                $this->id,
                $this->title,
                array( $this, 'render_smart_meta_box' ),
                $page,
                $this->context,
                $this->priority,
                $this->callback_args
            );
        }
    }

    function save_smart_meta_box_data( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        if ( !wp_verify_nonce( @$_POST[$this->prefix . 'nonce_field'], basename( __FILE__ ) ) )
            return;

        if ( $this->post_ids ) {
            if ( !in_array( $post_id, $this->post_ids ) )
                return false;
        }


        foreach ( $this->properties['fields'] as $field ) {
            $value = $_POST[$field['name']];
            update_post_meta( $post_id, $field['name'], $value );
        }

		if ( $this->save_callback ) {
			$args = array( $post_id, $post, $this->fields );
			call_user_func( $this->save_callback );
		}
    }

    function render_smart_meta_box() {
        global $post;
        wp_nonce_field( basename( __FILE__ ), $this->prefix . 'nonce_field' );

        $custom_keys = get_post_custom_keys( $post->ID );

        echo "<table class='form-table'>";
        foreach ( $this->properties['fields'] as $field ) {
            if ( $custom_keys && in_array( $field['name'], $custom_keys ) ) {
                $value = get_post_meta( $post->ID, $field['name'], true );
            } else {
                $value = '';
            }
            echo "<tr>";
            echo "  <td>";
            echo "     <label for='{$field['name']}'>";
            echo          $field['label'];
            echo "      </label>";
            echo "  </td>";
            echo "  <td>";
            if ( $field['type'] == "richtext" ) {
                echo "      <textarea class='theEditor' name='{$field['name']}' rows='5' cols='30'>{$value}</textarea>";
                echo "      {$field['desc']}";
            } else if ( $field['type'] == "media_file" ) {
?>
                <script language="JavaScript">
                    jQuery(document).ready(function() {
                        jQuery('#<?php echo $field['name']; ?>_button').click(function() {
                            formfield = jQuery('#<?php echo $field['name']; ?>').attr('name');
                            tb_show('', 'media-upload.php?type=image&TB_iframe=true');
                            return false;
                        });

                        window.send_to_editor = function(html) {
                            imgurl = jQuery('img',html).attr('src');
                            jQuery('#<?php echo $field['name']; ?>').val(imgurl);
                            tb_remove();
                        }
                    });
                </script>

    <?php
                echo "      <input type='{$field['type']}' name='{$field['name']}' id='{$field['name']}' value='{$value}'/>";
                echo "       <input id='{$field['name']}_button' type='button' value='Upload Image' />";
                echo "      {$field['desc']}";
            } else {
                echo "      <input type='{$field['type']}' name='{$field['name']}' size='{$field['size']}' value='{$value}' />";
                echo "      {$field['desc']}";
            }
            echo "  </td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
?>
