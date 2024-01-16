<?php
namespace GatherContent\Importer\Admin\Mapping\Field_Types;

use GatherContent\Importer\Views\View;

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
        $field_groups = acf_get_field_groups();
        ?>
        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>
            <select id="field-group-select" class="gc-select2 gc-select2-add-new wp-type-value-select field-select-group <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <# _.each( <?php echo json_encode($field_groups); ?>, function( group ) { #>
                    <option value="{{ group.key }}">{{ group.title }}</option>
                <# }); #>
                <?php $this->underscore_empty_option( __( 'Do Not Import', 'gathercontent-import' ) ); ?>
            </select>
            <select id="field-select" class="gc-select2 gc-select2-add-new wp-type-value-select field-select <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][field]">
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

        $(document).on('change', '.field-select-group', function() {

            // AJAX FIELD GROUP CHILD OPTIONS
            let group_key = $(this).val();
            let select_field = $(this).siblings('.field-select');
            let select_fields = $(this).parent('.column').siblings('.column').find('.component-child');
            let select_options = $(this).children('option');
            $(select_fields).empty();
            select_fields.append($('<option></option>').attr('value', '').text('Unused'));
            //console.log(select_options);
            $(select_options).each(function() {
                let option_value = $(this).val();
                console.log(option_value);
                if($(this).val() === group_key) {
                    console.log('match');
                    $(this).attr('selected','selected');
                }
            })


            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action : 'gc_get_fields_for_group',
                    group_key : group_key
                },
                success: function(response) {
                    var fields = response;
                    var fieldSelect = select_field;
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
                    var fields = response;
                    var fieldSelect = select_fields;
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
    }); 
</script>