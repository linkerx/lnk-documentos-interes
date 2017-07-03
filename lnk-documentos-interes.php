<?php

/**
 * Plugin Name: LNK-MP Documentos de Interés
 * Plugin URI: https://github.com/linkerx/lnk-documentos-interes
 * Description: Tipo de Dato Documento de interés para Comunicación MP
 * Version: 1
 * Author: Diego Martinez Diaz
 * Author URI: https://github.com/linkerx
 * License: GPLv3
 */

/**
 * Genera el tipo de dato formulario
 */
function lnk_documentos_create_type(){
    register_post_type(
        'documento',
        array(
            'labels' => array(
                'name' => __('Documentos','documentos_name'),
                'singular_name' => __('Documento','documentos_singular_name'),
                'menu_name' => __('Documentos de interés','documentos_menu_name'),
                'all_items' => __('Lista de Formularios','documentos_all_items'),
            ),
            'description' => 'Tipo de dato para documento de interés',
            'public' => true,
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 8,
            'support' => array(
                'title',
                'editor',
                'revisions'
            ),
            "capability_type" => 'documentos',
            "map_meta_cap" => true
        )
    );
}
add_action('init', 'lnk_documentos_create_type');

/**
 * Registra la taxonomía asociada al documento (Tipo de documento)
 */
function register_documentos_taxonomies(){

    $labels = array(
        'name' => "Tipos de documento",
        'singular_name' => "Tipo de documento",
    );
    $args = array(
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'update_count_callback' => '_update_post_term_count',
        'query_var' => true,
        'rewrite' => array('slug'=>'tipo_documento'),
    );
    register_taxonomy('tipo_documento','documento',$args);
}
add_action( 'init', 'register_documentos_taxonomies');

/**
 * Agrega la columna "Ver/Descargar" al listado de documentos
 * @param array $columns Listado de columnas
 */
function lnk_documentos_add_columns($columns) {
    global $post_type;
    if($post_type == 'documento'){
        $columns['lnk_documento_link'] = "Ver/Descargar";
    }
    return $columns;
}
add_filter ('manage_posts_columns', 'lnk_documentos_add_columns');

/**
 * Agrega el dato en la columna "Ver/Descargar" del listado de backend de documentos
 * @param array $columns Listado de columnas
 */
function lnk_documentos_show_columns_values($column_name) {
    global $wpdb, $post;
    $id = $post->ID;

    if($post->post_type == 'documento'){
        $id = $post->ID;
        if($column_name === 'lnk_documento_imagen'){
            echo "Link";
        }
    }
}
add_action ('manage_posts_custom_column', 'lnk_documentos_show_columns_values');

/**
 * Carga css y js en el head cuando esta en la administracion de documentos
 * @param string $hook
 */
function lnk_documentos_admin_head($hook) {
	global $post_type;

	$plugindir = get_option('siteurl').'/wp-content/plugins/'.plugin_basename(dirname(__FILE__));
	if($hook == 'post.php' || $hook == 'post-new.php' || $hook == 'edit.php')
	{
		if($post_type == 'documento')
		{
			wp_enqueue_script('lnk_documentos_admin_js',$plugindir.'/js/admin.js');
			wp_enqueue_style('lnk_documentos_admin_css',$plugindir.'/css/admin.css');
		}
	}
}
add_action("admin_enqueue_scripts",'lnk_documentos_admin_head' );

/**
 * Agrega los hooks para los datos meta en el editor de documentos
 */
function lnk_documentos_custom_meta() {
    global $post;
    if($post->post_type == 'documento'){
        add_meta_box('lnk_documento_file',"Archivo del Documento", 'lnk_documentos_file_meta_box', null, 'normal','core');
    }
}
add_action ('add_meta_boxes','lnk_documentos_custom_meta');

/**
 * Genera la vista del meta-box de carga del archivo al documento
 */
function lnk_documentos_file_meta_box() {
    global $post;
    wp_nonce_field(plugin_basename(__FILE__), 'lnk_documentos_file_nonce');

    if($archivo = get_post_meta( $post->ID, 'lnk_documentos_file', true )) {
        print "Archivo cargado: ".$archivo['url'];
    }

    echo '<p class="description">Seleccione su PDF aqui para reemplazar el existente</p>';
    echo '<input type="file" id="lnk_documentos_file" name="lnk_documentos_file" value="" size="25">';
}

/**
 * Agrega el tipo de encoding a la cabecera del formulario
 */
function lnk_documentos_update_edit_form() {
    echo ' enctype="multipart/form-data"';
}
add_action('post_edit_form_tag', 'lnk_documentos_update_edit_form');

/**
 * Agrega el guardado del archivo al documento
 */
function lnk_documentos_save_post_meta($id) {
    global $wpdb,$post_type;
    if($post_type == 'documento'){
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return $id;
        if (defined('DOING_AJAX') && DOING_AJAX)
                return $id;

        if(!empty($_FILES['lnk_documentos_file']['name'])) {
            $supported_types = lnk_documentos_supported_mime_types();
            $arr_file_type = wp_check_filetype(basename($_FILES['lnk_documentos_file']['name']));
            $uploaded_type = $arr_file_type['type'];

            if(in_array($uploaded_type, $supported_types)) {
                $upload = wp_upload_bits($_FILES['lnk_documentos_file']['name'], null, file_get_contents($_FILES['lnk_documentos_file']['tmp_name']));
                if(isset($upload['error']) && $upload['error'] != 0) {
                    wp_die('There was an error uploading your file. The error is: ' . $upload['error']);
                } else {
                    update_post_meta($id, 'lnk_documentos_file', $upload);
                }
            }
            else {
                wp_die("The file type that you've uploaded is not a PDF.");
            }
        }
    }


}
add_action('save_post','lnk_documentos_save_post_meta');

/**
 * Devuelve la lista de mime types soportados
 * pdf, doc, docx, odt, xls, xlsx, ods, jpg, png
 */
function lnk_documentos_supported_mime_types() {
  return array(
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.spreadsheet',
    'image/jpeg',
    'image/png'
  );
}
