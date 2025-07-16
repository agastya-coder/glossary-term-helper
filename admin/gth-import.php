<?php
/* Very small CSV importer */
add_action( 'admin_init', function () {
    if ( isset( $_GET['import'] ) && $_GET['import'] === 'gth_csv' ) {
        header( 'Content-Type: text/html; charset=utf-8' );
        echo '<h1>Upload CSV</h1>
              <form method="post" enctype="multipart/form-data">
              <input type="file" name="gth_csv" accept=".csv">
              <button class="button">Upload</button>
              </form>';
        if ( ! empty( $_FILES['gth_csv']['tmp_name'] ) ) {
            $fp = fopen( $_FILES['gth_csv']['tmp_name'], 'r' );
            while ( ( $row = fgetcsv( $fp ) ) !== false ) {
                [$term, $desc, $syn] = $row;
                $pid = wp_insert_post( [
                    'post_type'   => 'gth_term',
                    'post_title'  => $term,
                    'post_content'=> $desc,
                    'post_status' => 'publish',
                ] );
                update_post_meta( $pid, '_gth_synonyms', $syn );
            }
            echo '<p>Done. <a href="edit.php?post_type=gth_term">View terms</a></p>';
        }
        exit;
    }
} );
