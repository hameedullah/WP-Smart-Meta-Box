<?php

if ( ! class_exists( 'SmartMetaBoxField' ) ) :
class SmartMetaBoxField {
    function __construct( $field ) {
        $this->properties = $field;
    }
}
endif; // end if class SmartMetaBoxField exists
