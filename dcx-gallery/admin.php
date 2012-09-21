<?php
if (is_admin()){

/* Call the html code */
add_action('admin_menu', 'hello_world_admin_menu');

function hello_world_admin_menu() {
	add_options_page('Hello World', 'Hello World', 'administrator',
	'hello-world', 'hello_world_html_page');
	}
}
?>