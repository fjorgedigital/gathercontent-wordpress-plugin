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
        // echo json_encode($field_groups, JSON_PRETTY_PRINT);
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

        // echo json_encode($options, JSON_PRETTY_PRINT);
    
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
            <select class="hello gc-select2 gc-select2-add-new wp-type-value-select <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
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

        $mapping_fields = array();
        $field_groups = acf_get_field_groups();
        $mapping_id = absint( $this->_get_val( 'mapping' ) );
        
        $query = "SELECT post_content FROM wp_posts WHERE ID = $mapping_id LIMIT 1";
        $results = $wpdb->get_results($query);
        foreach($results[0] as $key => $value) {
            $temp_mapping = json_decode($value, JSON_PRETTY_PRINT);
            array_push($mapping_fields, $temp_mapping['mapping']);
        }
        ?>
        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>

            <?php 

            foreach($mapping_fields[0] as $key => $fields) { 

                // IF data.name from api and the content key match
                ?>
                <# if ( '<?php echo $key; ?>' == data.name ) { #>
                    <?php 
                    
                    foreach($fields as $child_key => $child_fields) {
                        if($child_key == 'field') {
                            $field_key = $child_fields;
                        }
                    }

                    ?>
                <# } #>

            <?php } ?>
            <select id="field-group-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new wp-type-value-select field-select-group <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <# _.each( <?php echo json_encode($field_groups); ?>, function( group ) { #>
                    <option data-group="{{group.key}}" <# if ( group.key === data.field_value ) { #>selected="selected"<# } #> data-set="{{data.field_value}}" value="{{ group.key }}">{{ group.title }}</option>
                <# }); #>
                <?php $this->underscore_empty_option( __( 'Do Not Import', 'gathercontent-import' ) ); ?>
            </select>
            <span style="display: block; margin: 5px 0;"></span>
            <select id="field-select-{{data.name}}" data-field-value="<?php echo $field_key; ?>" class="wp-type-value-select gc-select2 gc-select2-add-new field-select <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][field]">
                <!-- Options will be populated dynamically -->
            </select>
            
        <# } #>
        <?php
    }
}

?>

<script src="https://code.jquery.com/jquery-migrate-3.3.2.min.js"></script>

<script>
    jQuery(document).ready(function($) {

        // Set FIELD if saved
        function set_field() {
            $('.field-select').each(function() {
                let select_options = $(this).children('option');
                let field_name = $(this).attr('data-field-value');
                if(field_name) {
                    $(select_options).each(function() {
                        let option_value = $(this).val();
                        if($(this).val() === field_name) {
                            $(this).attr('selected','selected');
                        } else {
                            $(this).attr('selected',null);
                        }
                    });
                }
            });
        }

        setTimeout(function() {
            field_group_check();
        },300);

        $(document).on('change', '.field-select-group', function() {

            //AJAX FIELD GROUP CHILD OPTIONS
            let group_key = $(this).val();
            let select_field = $(this).siblings('.field-select');
            let select_fields = $(this).parent('.column').siblings('.column').find('.component-child');
            let select_options = $(this).children('option');
            $(select_fields).empty();
            select_fields.append($('<option></option>').attr('value', '').text('Unused'));

            // ADDS SELECTED 
            $(select_options).each(function() {
                let option_value = $(this).val();
                if($(this).val() === group_key) {
                    $(this).attr('selected','selected');
                } else {
                    $(this).attr('selected',null);
                }
            });

            // AJAX LOAD FIELD GROUP
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action : 'gc_get_fields_for_group',
                    group_key : group_key
                },
                success: function(response) {
                    let fields = response;
                    let fieldSelect = select_field;
                    fieldSelect.empty();
                    fieldSelect.append($('<option></option>').attr('value', '').text('Unused'));
                    $.each(fields, function(key, field) {
                        fieldSelect.append($('<option></option>').attr('value', field.key).text(field.label));
                    });
                },
                error: function (xhr, status, error) {
                    console.log('AJAX Request Error:');
                    console.log('Status:', status);
                    console.log('Error:', error);
                    console.log('Response Text:', xhr.responseText);
                    console.log('Data Sent:', xhr.data); // Log the data sent in the request
                }
            });
        });

        $(document).on('change', '.field-select', function() {

            // AJAX FIELD GROUP CHILD OPTIONS
            let field_parent = $(this).siblings('.field-select-group').val();
            let field_val = $(this).val();
            let select_fields = $(this).parent('.column').siblings('.column').find('.component-child');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action : 'gc_get_fields',
                    field_parent : field_parent
                },
                success: function(response) {
                    let fields = response;
                    let fieldSelect = select_fields;
                    fieldSelect.empty();
                    $.each(fields, function(key, field) {
                        if(field.key == field_val) {
                            fieldSelect.append($('<option></option>').attr('value', '').text('Unused'));
                            $.each(field.sub_fields, function(key, field) {
                                fieldSelect.append($('<option></option>').attr('value', field.key).text(field.label));
                            });
                        }
                        
                    });
                },
                error: function (xhr, status, error) {
                    console.log('AJAX Request Error:');
                    console.log('Status:', status);
                    console.log('Error:', error);
                    console.log('Response Text:', xhr.responseText);
                    console.log('Data Sent:', xhr.data); // Log the data sent in the request
                }
            });
        });

        function field_group_check() {
            $('.field-select-group').each(function() {
                let field_set = $(this).attr('data-set');

                if(field_set){
                    //AJAX FIELD GROUP CHILD OPTIONS
                    let group_key = $(this).val();
                    let select_field = $(this).siblings('.field-select');
                    let select_fields = $(this).parent('.column').siblings('.column').find('.component-child');
                    let select_options = $(this).children('option');
                    $(select_fields).empty();
                    select_fields.append($('<option></option>').attr('value', '').text('Unused'));

                    // ADDS SELECTED 
                    $(select_options).each(function() {
                        let option_value = $(this).val();
                        if($(this).val() === group_key) {
                            $(this).attr('selected','selected');
                        } else {
                            $(this).attr('selected',null);
                        }
                    });

                    // AJAX LOAD FIELD GROUP
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'JSON',
                        data: {
                            action : 'gc_get_fields_for_group',
                            group_key : group_key
                        },
                        success: function(response) {
                            let fields = response;
                            let fieldSelect = select_field;
                            fieldSelect.empty();
                            fieldSelect.append($('<option></option>').attr('value', '').text('Unused'));
                            $.each(fields, function(key, field) {
                                fieldSelect.append($('<option></option>').attr('value', field.key).text(field.label));
                            });
                            set_field();
                        },
                        error: function (xhr, status, error) {
                            console.log('AJAX Request Error:');
                            console.log('Status:', status);
                            console.log('Error:', error);
                            console.log('Response Text:', xhr.responseText);
                            console.log('Data Sent:', xhr.data); // Log the data sent in the request
                        }
                    });
                }
            });
        }


    }); 
</script>