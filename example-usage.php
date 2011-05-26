<?php

//setup example usage here

$fields = array();
$fields[]  = array(
    'name' => 'friend_username',
    'label' => 'Friend\'s Username',
    'type' => 'text',
    'desc' => 'What is your Friends name?',
    'value' => '@exmaple_name',
    'skip_filter' => true,
    'size' => 20
);

$box = array( 'context' => 'side', 'fields' => $fields );
new SmartMetaBox( $box );
?>
