<?php
namespace GatherContent\Importer\Admin\Mapping\Field_Types;

use GatherContent\Importer\Views\View;
use WP_Query;

class ACF extends Base implements Type {


    protected $type_id = 'wp-type-acf';

    /**
     * Array of supported template field types.
     *
     * @var array
     */
    protected $supported_types = array(
        'component',
        'repeater',
        'text_rich',
    );

    /**
     * Creates an instance of this class.
     *
     * @since 3.0.0
     */
    public function __construct() {
        $this->option_label = __( 'ACF Field Groups', 'gathercontent-import' );
    }

    public function underscore_template_1( View $view ) {
        $options = array();
    
        $field_groups = acf_get_field_groups();

        foreach ( $field_groups as $group ) {
            // print_r($group);
            $fields = get_posts(array(
                'posts_per_page'   => -1,
                'post_type'        => 'acf-field',
                'orderby'          => 'menu_order',
                'order'            => 'ASC',
                'suppress_filters' => true,
                'post_parent'      => $group['ID'],
                'post_status'      => 'any',
                'update_post_meta_cache' => false
            ));
            foreach ( $fields as $field ) {
                $options[$field->post_name] = $field->post_title;
            }
        }
    
        ?>
        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>
            <select class="gc-select2 gc-select2-add-new wp-type-value-select <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <# _.each( <?php echo json_encode($options); ?>, function( title, name ) { #>
                    <option <# if ( name === data.field_value ) { #>selected="selected"<# } #> value="{{ name }}">{{ title }}</option>
                <# }); #>
                <?php $this->underscore_empty_option( __( 'Do Not Import', 'gathercontent-import' ) ); ?>
            </select>
        <# } #>
        <?php
    }
    
    public function underscore_template_2( View $view ) {
        $field_groups = acf_get_field_groups();
        ?>
        
        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>
            <select class="gc-select2 gc-select2-add-new wp-type-value-select <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <# _.each( <?php echo json_encode($field_groups); ?>, function( group ) { #>
                    <option value="{{ group.key }}">{{ group.title }}</option>
                <# }); #>
                <?php $this->underscore_empty_option( __( 'Do Not Import', 'gathercontent-import' ) ); ?>
            </select>
        <# } #>
        <?php
    }

    public function underscore_template ( View $view ) {
        global $wpdb;
        global $wp_query;

        // VARIABLES
        $mapping_id = absint( $this->_get_val( 'mapping' ) );

        // FIELD GROUPS
        $groups = "SELECT * FROM wp_posts WHERE post_type = 'acf-field-group' AND post_status = 'publish' AND post_parent = 0";
        $group_results = $wpdb->get_results($groups);

        // CUSTOM VARIABLES
        $data_results = array();
        
        // FIELD GROUPS
        $group_results = $wpdb->get_results($groups);
        $group_fields = array();
        foreach($group_results as $group) {
            $group_id = $group->ID;
            $group_key = $group->post_name;
            $fields_array = array();
            $fields_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field' AND post_parent = '$group_id'";
            $fields_results = $wpdb->get_results($fields_query);
            foreach($fields_results as $field) {
                $field_title = $field->post_title;
                $field_id = $field->ID;
                $field_name = $field->post_name;
                $field_sub_fields = "SELECT * FROM wp_posts WHERE post_type = 'acf-field' AND post_parent = '$field_id'";
                $sub_field_results = $wpdb->get_results($field_sub_fields);
                $sub_fields = array();
                if(!empty($sub_field_results)) {
                    foreach($sub_field_results as $sub_field) {
                        $sub_field_id = $sub_field->ID;
                        $sub_field_title = $sub_field->post_title;
                        $sub_field_name = $sub_field->post_name;
                        $sub_field_array = array('sub_field_id' => $sub_field_id, 'sub_field_title' => $sub_field_title, 'sub_field_name' => $sub_field_name);
                        array_push($sub_fields, $sub_field_array);
                    }
                }
                $field_fields = array('ID' => $field_id, 'post_title' => $field_title, 'post_name' => $field_name, 'sub_fields' => $sub_fields );
                $fields_array[] = $field_fields;
            }
            $group_fields['group_' . $group_id] = $fields_array;
        }
        $data_results['field_groups'] = $group_fields;
 
        // SAVED DATA
        $saved_data = array();
        $query = "SELECT post_content FROM wp_posts WHERE ID = $mapping_id LIMIT 1";
        $results = $wpdb->get_results($query);
        foreach($results[0] as $key => $value) {
            $temp_mapping = json_decode($value, JSON_PRETTY_PRINT);
            $saved_data['mapping'] = $temp_mapping['mapping'];
        }
        $data_results['saved'] = $saved_data;

        // DATA LOADING
        $results = json_encode($data_results,JSON_PRETTY_PRINT);
        ?>
        <div id="mapped" style="display:none;"><?php print_r($results); ?></div>

        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>

            <select id="field-group-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new field-select-group <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <option data-group="" data-set="" value="">Unused</option>
                 <# _.each( <?php echo json_encode($group_results); ?>, function( group ) { #>
                    <option data-group="{{group.post_name}}" <# if ( group.ID == data.field_value ) { #> selected="selected"<# } #> data-set="{{data.field_value}}" value="{{ group.ID }}">{{ group.post_title }}</option>
                <# }); #>
            </select>
            <span style="display: block; margin: 5px 0;"></span>
            <select id="field-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new field-select <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][field]">
                <!-- Options will be populated dynamically -->
            </select>
            
        <# } #>
<?php } } ?>

<script src="https://code.jquery.com/jquery-migrate-3.3.2.min.js"></script>

<script>
    jQuery(document).ready(function($) {
        
        /******************* TEMPLATE MAPPING ************************/

        // GET ACF FIELD GROUPS & SAVED DATA
        // -- Type Select
        // -- Group Select
        // -- Field Select
        // -- Sub Fields Select
        let data = '';
        let field_groups = '';
        let saved_fields = '';
        setTimeout(function() {
            data = $('#mapped').html();
            data = JSON.parse(data); // GET PRINTED OUT DATA
            if(data) {
                field_groups = data['field_groups'];
                saved_fields = data['saved']['mapping'];
            }
            load_functions();
        },200);

        
        // LOAD FUNCTIONS
        function load_functions() {
            component_init();
            type_select();
            group_init();
            group_select();
            fields_init();
            fields_select();
        }

        // CLEAT COMPONENT CHILDREN
        function component_init() {
            $('select.component-child').each(function() {
                $(this).empty();
                $(this).append('<option val="">Unused</option>')
            });
        }

        // TYPE CHANGE
        function type_select() {
            $(document).on('change','.type-select',function() {
                fields_select();
                component_init();
            })
        }

        // GET GROUPS
        function group_init() {
            $('.field-select-group').each(function() {
                let select_id = $(this).attr('id');
                get_group_fields(select_id);
            });
        }

        // GROUP CHANGE
        function group_select() {
            $(document).on('change','.field-select-group',function() {
                let select_id = $(this).attr('id');
                get_group_fields(select_id);
                component_init();
            });
        }

        // GET FIELDS
        function fields_init() {
            $('.field-select').each(function() {
                let select_id = $(this).attr('id');
                let action = 'init';
                get_field_fields(select_id,action);
            });
        }

        // FIELD SELECT
        function fields_select() {
            $(document).on('change','.field-select',function() {
                let select_id = $(this).attr('id');
                let action = 'change';
                get_field_fields(select_id,action);
            });
        }

        // GET FIELDS
        function get_field_fields(select_id,action) {
                
            // DATA SET
            let data_set = $('#' + select_id).attr('data-set');

            // SAVED DATA
            let saved_field = '';
            let saved_sub_fields = '';
            if($(saved_fields).length > 0) {
                saved_field = saved_fields[data_set]['field'];
                saved_sub_fields = saved_fields[data_set]['sub_fields'];
            }

            // FIELD OPTIONS
            let components = $('.acf-components[data-set="' + data_set + '"').children('select');
            let field_id = '';
            let field_group = '';

            // GET REPREATER GROUP
            $('#' + select_id).children('option').each(function() {
                let field_value = $(this).val();
                if(action == 'init') {
                    if(field_value == saved_field) {
                        field_id = $(this).attr('data-field-id');
                        field_group = $(this).attr('data-field-group');
                        $(this).attr('selected','selected');
                    }
                } else {
                    if($(this).is(':selected')) {
                        field_id = $(this).attr('data-field-id');
                        field_group = $(this).attr('data-field-group');
                    }
                }
            });
            
            // GET SUB FIELDS
            let parent_fields = field_groups[field_group];
            let sub_fields = '';
            $.each(parent_fields, function(key, field) {
                let parent_field_id = field['ID'];
                if(field_id == parent_field_id) {
                    sub_fields = field['sub_fields'];
                }
            });
            if(components) {
                $(components).each(function() {
                    let component = $(this);
                    let data_index = '';
                    let saved_sub_field = '';
                    if(saved_sub_fields) {
                        data_index = $(this).attr('data-index');
                        saved_sub_field = saved_sub_fields[data_index];
                    }
                    $(this).empty();
                    component.append($('<option data-field-id=""></option>').attr('value', '').text('Unused'));
                    if(sub_fields) {
                        $.each(sub_fields, function(key, field) {
                            if(saved_sub_field == field['sub_field_name']) {
                                component.append($('<option data-field-id="' + field['sub_field_id'] + '" selected="selected"></option>').attr('value', field['sub_field_name']).text(field['sub_field_title']));
                            } else {
                                component.append($('<option data-field-id="' + field['sub_field_id'] + '"></option>').attr('value', field['sub_field_name']).text(field['sub_field_title']));
                            }
                        });
                    }

                });
            }
        }

        // GET GROUPS
        function get_group_fields(select_id) {
            let select_field = select_id;
            if(field_groups) {
                let data_set = $('#' + select_id).attr('data-set');
                let group_id = $('#' + select_id).val();
                let field_group = 'group_' + group_id;
                let fields = field_groups[field_group];
                let field_select = $('#field-select-' + data_set);
                field_select.empty();
                field_select.append($('<option></option>').attr('value', '').text('Unused'));
                $.each(fields, function(key, field) {
                    let field_id = '';
                    let title = '';
                    let field_name = '';
                    field_id = field['ID'];
                    title = field['post_title'];
                    field_name = field['post_name'];
                    if(title && field_name) {
                        field_select.append($('<option data-field-group="group_' + group_id + '" data-field-id="' + field_id + '"></option>').attr('value', field_name).text(title));
                    } 
                });
            }
        }

    }); 
</script>