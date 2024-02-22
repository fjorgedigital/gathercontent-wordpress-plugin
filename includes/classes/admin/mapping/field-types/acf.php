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

    public function underscore_template ( View $view ) {
        global $wpdb;
        global $wp_query;

        // VARIABLES
        $data_results = array();

        /******************* BUILD FIELD GROUP OPTIONS ************************/
        $groups_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field-group' AND post_status = 'publish' AND post_parent = 0";
        $group_results = $wpdb->get_results($groups_query);
        
        // FIELD GROUPS
        $group_fields = array();
        if($group_results){
            foreach($group_results as $group) {
                $fields_array = array();
                // $fields_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field' AND post_content LIKE '%repeater%' AND post_parent = '$group->ID'"; // Use to get ONLY repeaters
                $fields_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field' AND post_parent = '$group->ID'";
                $fields_results = $wpdb->get_results($fields_query);

                // Loop fields within each ACF Field Group
                if($fields_results){
                    foreach($fields_results as $field) {
                        $field_sub_fields_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field' AND post_parent = '$field->ID'";
                        $sub_field_results = $wpdb->get_results($field_sub_fields_query);
                        $sub_fields = array();

                        // If field has sub-fields, loop sub-fields. ie: Repeaters, Groups, Flexible Content
                        if(!empty($sub_field_results)) {
                            foreach($sub_field_results as $sub_field) {
                                $sub_field_array = array('sub_field_id' => $sub_field->ID, 'sub_field_title' => $sub_field->post_title, 'sub_field_name' => $sub_field->post_name);
                                array_push($sub_fields, $sub_field_array);
                            }
                        }
                        // Build Fields array, including sub-fields
                        $field_fields = array('ID' => $field->ID, 'post_title' => $field->post_title, 'post_name' => $field->post_name, 'sub_fields' => $sub_fields );
                        $fields_array[] = $field_fields;
                    }
                }
                // Insert fields array to group by group_id
                $group_fields['group_' . $group->ID] = $fields_array;
            }
        }
        $data_results['field_groups'] = $group_fields;


        /******************* GET SAVED MAPPING DATA ************************/
        $mapping_id = absint( $this->_get_val( 'mapping' ) );
        $saved_data = array();
        $mapped_post_type = '';
        $mapping_query = "SELECT post_content FROM wp_posts WHERE ID = $mapping_id LIMIT 1";
        $mapping_results = $wpdb->get_results($mapping_query);
        if($mapping_results){
            foreach($mapping_results[0] as $key => $value) {
                $mapping_parsed = json_decode($value, JSON_PRETTY_PRINT);
                $mapped_post_type = $mapping_parsed['post_type'];
                $saved_data['mapping'] = $mapping_parsed['mapping'];
            }
        }
        $data_results['saved'] = $saved_data;


        // UPDATE SAVED FIELD GROUPS
        if($saved_data && $mapped_post_type) {
            foreach($saved_data as $saved_group) {
                foreach($saved_group as $data) {
                    if($data['type'] == 'wp-type-acf') {
                        $post_name = $data['value'];
                        $group_query = "SELECT * FROM wp_posts WHERE post_name = '$post_name' LIMIT 1";
                        $query_results = $wpdb->get_results($group_query);
                        if($query_results){
                            foreach($query_results as $post_data) {
                                $post_id = $post_data->ID;
                                $post_info = maybe_unserialize($post_data);
                                $post_content_meta = maybe_unserialize($post_info->post_content);
                                $post_type_data = array(array('param' => 'post_type', 'operator' => '==', 'value' => $mapped_post_type));
                                if(!in_array($post_type_data,$post_content_meta['location'])) {
                                    array_push($post_content_meta['location'],$post_type_data);
                                    $updated_content = serialize($post_content_meta);
                                    $data = array(
                                        'ID' => $post_id,
                                        'post_content' => $updated_content,
                                    );
                                    wp_update_post( $data );
                                }
                            }
                        }
                    }
                }
            }
        }
        


        // DATA LOADING
        $results = json_encode($data_results,JSON_PRETTY_PRINT);
        ?>
        <div id="mapped" style="display:none;"><?php print_r($results); ?></div>

        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>

            <select id="field-group-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new field-select-group <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <option data-group="" data-set="" value="">Unused</option>
                 <# _.each( <?php echo json_encode($group_results); ?>, function( group ) { #>
                    <option data-group="{{group.post_name}}" <# if ( group.post_name == data.field_value ) { #> selected="selected"<# } #> data-set="{{data.field_value}}" value="{{ group.post_name }}" data-group-id="{{ group.ID }}">{{ group.post_title }}</option>
                <# }); #>
            </select>
            <span style="display: block; margin: 5px 0;"></span>
            <select id="field-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new field-select-field <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][field]">
                <!-- Options will be populated dynamically -->
            </select>
            
        <# } #>
<?php } } ?>

<script src="https://code.jquery.com/jquery-migrate-3.3.2.min.js"></script>

<script>
    jQuery(window).on('load', function($) {
        var $ = jQuery;

        // Global Variables
        let data = '';
        let field_groups = '';
        let saved_fields = '';

        /**
         * For new mappings, when the user needs to select the post type
         * fire init functions on page change
         */
        $(document).on('change', '#default-mapping-post_type select', function(){
            console.log('post type selected!');
            gc_acf_select_init();
        });
        // Also fire on page load for when the user is editing an existing mapping
        gc_acf_select_init();

        
        /******************* TEMPLATE MAPPING ************************/

        // GET ACF FIELD GROUPS & SAVED DATA
        // -- Type Select
        // -- Group Select
        // -- Field Select
        // -- Sub Fields Select
        function gc_acf_select_init(){
            console.log('gc_acf_select_init');

            if( !$('#mapping-defaults').is(':visible') ){
                var interval;
                interval = setInterval(function(){
                    console.log('interval hit');
                    data = $('#mapped').html();
                    console.log('data 1: ', data);

                    // Loop until data is retrieved, then clear
                    if(data) {
                        clearInterval(interval);

                        // GET Data and init layout
                        data = JSON.parse(data); 
                        console.log('data 2: ', data);
                        field_groups = data['field_groups'];
                        saved_fields = data['saved']['mapping'];
                        console.log('field_groups: ', field_groups);
                        console.log('saved_fields: ', saved_fields);
                    
                        // Init Functions
                        gc_type_select();
                        gc_group_init();
                        gc_group_select();
                        gc_fields_init();
                        gc_fields_select();
                    }
                }, 200);
            }
        }
    

        /****** INIT FUNCTIONS ******/
        // GET GROUPS
        function gc_group_init() {
            $('.field-select-group').each(function() {
                console.log('gc_group_init');
                let select_id = $(this).attr('id');
                get_group_fields(select_id);
            });
        }

        // GET FIELDS
        function gc_fields_init() {
            $('.field-select-field').each(function() {
                console.log('gc_fields_init');
                let select_id = $(this).attr('id');
                let action = 'init';
                gc_get_field_fields(select_id, action);
            });
        }


        /****** LISTENER FUNCTIONS ******/
        // TYPE CHANGE
        function gc_type_select() {
            $(document).on('change','.type-select',function() {
                console.log('gc_type_select');
                gc_fields_select();
            })
        }

        // GROUP CHANGE
        function gc_group_select() {
            $(document).on('change','.field-select-group',function() {
                console.log('gc_group_select');
                let select_id = $(this).attr('id');
                get_group_fields(select_id);
            });
        }

        // FIELD SELECT
        function gc_fields_select() {
            $(document).on('change','.field-select-field',function() {
                console.log('gc_fields_select');
                let select_id = $(this).attr('id');
                let action = 'change';
                gc_get_field_fields(select_id, action);
            });
        }


        /****** HELPER FUNCTIONS ******/
        // GET FIELDS
        function gc_get_field_fields(select_id, action) {
            console.log('gc_get_field_fields');
            console.log('select_id:', select_id);
            console.log('action: ', action);
            // DATA SET
            let data_set = $('#' + select_id).attr('data-set');
            console.log('data_set: ', data_set);

            // SAVED DATA
            let saved_field = '';
            let saved_sub_fields = '';
            if(saved_fields && saved_fields[data_set]) {
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
                        $(this).attr('selected', 'selected');
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
            console.log('get_group_fields - select_id: ',select_id);
            let select_field = select_id;
            if(field_groups) {
                let data_set = $('#' + select_id).attr('data-set');
                let data_val = $('#' + select_id).val();
                let group_id = '';
                let child_options = $('#' + select_id).children('option');
                $(child_options).each(function() {
                    let child_group = $(this).attr('data-group');
                    let child_id = $(this).attr('data-group-id');
                    if(data_val == child_group) {
                        group_id = child_id;
                    }
                });
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