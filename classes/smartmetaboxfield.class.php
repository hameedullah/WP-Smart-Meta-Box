<?php

if ( ! class_exists( 'SmartMetaBoxField' ) ) :
class SmartMetaBoxField {
    /* TODO: Add support for multi value meta keys ;)
     * TODO: Add Taxonomies support
     * TODO: Add filter support
     */

    protected $default_properties = array(
        'name' => 'wp_smb_field_',
        'label' => 'WP Smart Meta Box Field',
        'type' => 'text',
        'desc' => '',
        'show_desc' => false,
        'value' => '',
        'skip_filters' => false,
        'size' => 20
    );

    // keep track of the global fields count
    public static $field_count = 1;

    function __construct( $field, $meta_box, $is_sub_field=false ) {
        $this->properties = $field;
        $this->meta_box = $meta_box;
        $this->_is_sub_field = $is_sub_field;

        $this->setup_properties();

        $field_number = self::$field_count;

        if ( $this->type == "container" ) {
            $total_sub_fields = 0;
            foreach ( $this->sub_fields as $sub_field ) {
                $sub_field['_id'] = $field_number;
                $field_number += 1;
                $total_sub_fields += 1;
            }
            $this->meta_box->register_max_sub_fields_num( $total_sub_fields );

            $init_sub_field = true;
            $this->sub_fields = array_map( 
                create_function( '$field, $meta_box, $is_sub_field', 'return new SmartMetaBoxField( $field, $meta_box, $is_sub_field );' ),
                $this->sub_fields,
                array_fill( 0, $total_sub_fields, $this->meta_box ),
                array_fill( 0, $total_sub_fields, $init_sub_field )
            );
        }



        // if this is not sub field then set the id
        // sub fields get their ids assigned in their container field
        if ( !$this->_is_sub_field ) {
            $this->_id = $field_number;

            $field_number += 1;

            // set the global counter to the new field number
            self::$field_count = $field_number;
        }

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
        /* TODO: Add sanitizing of all properties */
        return $properties;
    }

    function render() {

        echo "<tr>";
        if ( $this->type == "container" ) {
            foreach ( $this->sub_fields as $sub_field ) {
                $sub_field->field_render();

            }
        } else {
            $this->field_render();
        }
        echo "</tr>";
    }

    function field_render() {
        global $post;
        if ( $this->meta_box->custom_keys && in_array( $this->name, $this->meta_box->custom_keys ) ) {
            $value = get_post_meta( $post->ID, $this->name, true );
            $this->value = $value;
        }

        $col_span = "";
        if ( !$this->_is_sub_field ) {
            // get the number of maximum sub fields registered in this metabox
            $max_sub_fields = $this->meta_box->_max_sub_fields;
            $col_span_count = ( $max_sub_fields * 2 ) - 1;
            $col_span = " colspan='$col_span_count'";
        }
        echo "  <td>";
        echo "     <label for='{$this->name}'>";
        echo          $this->label;
        echo "      </label>";
        echo "  </td>";
        echo "  <td$col_span>";
        if ( $this->type == "richtext" ) {
            /* FIX: the default editor media upload box adds images to the smartmetabox richtext editor
			 * TODO: Add show/hide toggle button support
			 * TODO: Add support for default visual editor state
             */
			echo "<a href='#' onclick='smb_enabletinymce(\"$this->name\"); return false;'>Toggle Visual Editor</a><br />";
            echo "      <textarea class='smartCustomEditor' id='{$this->name}' name='{$this->name}' rows='5' cols='30'>{$this->value}</textarea>";
        } else if ( $this->type == "post_editor" ) {
            /* TODO: Add the media buttons option, i.e 4th argument to the_editor 
             */
            the_editor( $this->value, $this->name );
        } else if ( $this->type == "media_file" ) {
?>
            <script language="JavaScript">
                jQuery(document).ready(function() {
                    jQuery('#<?php echo $this->name; ?>_button').click(function() {
                        window.default_send_to_editor = window.send_to_editor;
                        formfield = jQuery('#<?php echo $this->name; ?>').attr('name');
                        tb_show('', 'media-upload.php?type=image&TB_iframe=true');
                    window.send_to_editor = function(html) {
                        imgurl = jQuery('img',html).attr('src');
                        jQuery('#<?php echo $this->name; ?>').val(imgurl);
                        tb_remove();
                        window.send_to_editor = window.default_send_to_editor;
                    }

                        return false;
                    });

                });
            </script>

<?php
            echo "      <input type='text' name='{$this->name}' id='{$this->name}' value='{$this->value}'/>";
            echo "       <input id='{$this->name}_button' type='button' value='Upload Image' />";
        } else if ( $this->type == "textarea" ) {
            echo "      <textarea id='{$this->name}' name='{$this->name}' rows='{$this->rows}' cols='{$this->cols}'>{$this->value}</textarea>";
        } else {
            echo "      <input type='{$this->type}' name='{$this->name}' size='{$this->size}' value='{$this->value}' />";
        }
        if ( $this->show_desc === true )
            echo "      {$this->desc}";

        echo "  </td>";
    }

    function save_value( $post_id ) {
        if ( $this->type == "container" ) {
            foreach ( $this->sub_fields as $sub_field ) {
                $sub_field->save_value( $post_id );
            }
        } else {
            $value = $_POST[$this->name];
            update_post_meta( $post_id, $this->name, $value );
        }
    }

	public function setup_hooks() {
		global $wp_filter;
		_debug( $wp_filter['admin_head'] );
		add_action( 'admin_head', array( &$this, 'tinymce_js' ) );
	}

	public function tinymce_js() {
?>
		<script type='text/javascript'>
				function smb_enabletinymce(id) {
					    jQuery('#' + id).addClass("mceEditor");
						tinyMCE.settings = {
							theme: 'advanced',
							mode: 'none',
								theme_advanced_layout_manager: 'SimpleLayout',
								theme_advanced_toolbar_location: 'top',
								theme_advanced_toolbar_align: 'left',
								theme_advanced_buttons1: 'bold,italic,underline,strikethrough,|,bullist,numlist,|,outdent,indent',
								theme_advanced_buttons2: '',
								theme_advanced_buttons3: ''
						};

					tinyMCE.execCommand('mceToggleEditor', false,  id);
				}
            </script>
	<?php 
	}
}
endif; // end if class SmartMetaBoxField exists
