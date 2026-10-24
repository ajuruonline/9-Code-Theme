<?php
/** Disposable test-site menu fixtures; never packaged with the theme. */
add_action( 'admin_menu', function () {
    add_menu_page( 'Power Lecture', 'Power Lecture', 'read', 'power-lecture', function () {} );
    add_menu_page( 'Unrelated Marketing', 'Unrelated Marketing', 'read', 'marketing-fixture', function () {} );
} );
