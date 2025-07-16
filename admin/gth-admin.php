<?php
/* ----------  META BOX  ---------- */
add_action( 'add_meta_boxes_gth_term', function () {
    add_meta_box(
        'gth_synonyms',
        'Synonyms (comma-separated)',
        'gth_syn_box_html',
        'gth_term',
        'after_title',   // <- new context
        'high'           // <- priority
    );
} );

add_action( 'edit_form_after_title', function () {
    global $post;
    if ( $post->post_type !== 'gth_term' ) return;
    $val = get_post_meta( $post->ID, '_gth_synonyms', true );
    echo '<div class="postbox" style="margin-top:12px;"><h2>Synonyms (comma-separated)</h2>';
    echo '<div class="inside"><textarea name="gth_synonyms" style="width:100%;min-height:80px">'.esc_textarea( $val ).'</textarea></div></div>';
} );

function gth_syn_box_html( $post ) {
    $val = get_post_meta( $post->ID, '_gth_synonyms', true );
    echo '<textarea name="gth_synonyms" style="width:100%;min-height:80px">'.esc_textarea( $val ).'</textarea>';
}

add_action( 'save_post_gth_term', function ( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( isset( $_POST['gth_synonyms'] ) )
        update_post_meta( $post_id, '_gth_synonyms', sanitize_text_field( $_POST['gth_synonyms'] ) );
} );

/* ----------  REGISTER SETTINGS  ---------- */
add_action( 'admin_init', function () {
    register_setting( 'gth_options', 'gth_underline_color' );
    register_setting( 'gth_options', 'gth_underline_style' );
    register_setting( 'gth_options', 'gth_tooltip_bg' );
    register_setting( 'gth_options', 'gth_tooltip_text_color' );
    register_setting( 'gth_options', 'gth_tooltip_font_size' );
} );

/* ----------  MENU  ---------- */
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=gth_term',
        'GTH Style',
        'Style',
        'manage_options',
        'gth_style',
        'gth_style_page'
    );
} );

function gth_style_page() { ?>
    <div class="wrap">
        <h1>Glossary-Term-Helper Styling</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'gth_options' ); ?>
            <table class="form-table">
                <tr>
                    <th>Underline color</th>
                    <td><input type="color" name="gth_underline_color" value="<?php echo esc_attr( get_option( 'gth_underline_color', '#ffffff' ) ); ?>"></td>
                </tr>
                <tr>
                    <th>Underline style</th>
                    <td>
                        <select name="gth_underline_style">
                            <?php
                            $s = get_option( 'gth_underline_style', 'dotted' );
                            $opts = [ 'dotted','dashed','solid','double' ];
                            foreach ( $opts as $o ) printf( '<option value="%1$s"%2$s>%1$s</option>', $o, selected( $s, $o, false ) );
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Tooltip background</th>
                    <td><input type="color" name="gth_tooltip_bg" value="<?php echo esc_attr( get_option( 'gth_tooltip_bg', '#222' ) ); ?>"></td>
                </tr>
                <tr>
                    <th>Tooltip text color</th>
                    <td><input type="color" name="gth_tooltip_text_color" value="<?php echo esc_attr( get_option( 'gth_tooltip_text_color', '#fff' ) ); ?>"></td>
                </tr>
                <tr>
                    <th>Tooltip font-size (px)</th>
                    <td><input type="number" min="10" max="30" name="gth_tooltip_font_size" value="<?php echo esc_attr( get_option( 'gth_tooltip_font_size', 16 ) ); ?>"></td>
                </tr>
                <?php do_action( 'gth_style_page_after_table' ); ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php }
