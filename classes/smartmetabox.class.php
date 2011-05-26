<?php

class SmartMetaBox {

    protected $prefix;
    protected $id;
    protected $title;
    protected $pages;
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
        'callback' => '',
        'callback_args' => null,
        'context' => 'advanced',
        'priority' => 'default'
    );

    private $allowed_contexts = array( 'normal', 'advanced', 'side' );
    private $allowed_priorities = array( 'high', 'core', 'default', 'low' );

    public static $meta_box_count = 1;

    function __construct( $meta_box=null ) {
        $this->properties = $meta_box;
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

        if ( !in_array( $properties['context'], $this->allowed_contexts ) )
            $properties['context'] = $this->default_properties['context'];

        if ( !in_array( $properties['priority'], $this->allowed_priorities ) )
            $properties['priority'] = $this->default_properties['priority'];

        return $properties;
    }

    function setup_hooks() {
        add_action( 'add_meta_boxes', array( $this, 'add_smart_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_smart_meta_box_data' ) );
    }

    function add_smart_meta_box() {
        foreach( $this->pages as $page ) {
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
        self::$meta_box_count += 1;
    }

    function save_smart_meta_box_data() {
    }

    function render_smart_meta_box() {
        wp_nonce_field( basename( __FILE__ ), $this->prefix . 'nonce_field' );

        echo "<table class='form-table'>";
        foreach ( $this->properties['fields'] as $field ) {
            echo "<tr>";
            echo "  <td>";
            echo "     <label for='{$field[name]}'>";
            echo          $field['label'];
            echo "      </label>";
            echo "  </td>";
            echo "  <td>";
            echo "      <input type='{$field[type]}' name='{$field[name]}' size='{$field[size]}' value='{$field[value]}' />";
            echo "      {$field[desc]}";
            echo "  </td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
?>
