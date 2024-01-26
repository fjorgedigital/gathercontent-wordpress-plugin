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
        $group_options = array();
        $mapping_fields = array();
        $field_groups = acf_get_field_groups();
        $mapping_id = absint( $this->_get_val( 'mapping' ) );

        // FIELD GROUPS
        $groups = "SELECT * FROM wp_posts WHERE post_type = 'acf-field-group' AND post_status = 'publish' AND post_parent = 0";
        $group_results = $wpdb->get_results($groups);
        
        // SAVED FIELD DATA
        $query = "SELECT post_content FROM wp_posts WHERE ID = $mapping_id LIMIT 1";
        $results = $wpdb->get_results($query);
        foreach($results[0] as $key => $value) {
            $temp_mapping = json_decode($value, JSON_PRETTY_PRINT);
            array_push($mapping_fields, $temp_mapping['mapping']);
        }

        // GROUP FIELDS
        // $group_fields = array(); // Parent keys with child field arrays
        // foreach($field_groups as $group) {
        //     $group_id = $group['ID'];
        //     $group_key = $group['key'];
        //     $fields_array = array();
        //     $fields_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field' AND post_parent = '$group_id'";
        //     $fields_results = $wpdb->get_results($fields_query);
        //     foreach($fields_results as $field) {
        //         $field_title = $field->post_title;
        //         $field_id = $field->ID;
        //         $field_name = $field->post_name;
        //         $field_fields = array('ID' => $field_id, 'post_title' => $field_title, 'post_name' => $field_name);
        //         array_push($fields_array,$field_fields);
        //     }
        //     $group_fields[$group_id] = $fields_array;
        // }
        ?>
        <div class="mapping-id" data-template-id="<?php echo $mapping_id; ?>" style="display: none;">Mapping Template ID: <?php echo $mapping_id; ?></div>

        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>

        <!-- <pre>{{ JSON.stringify(data, null, ) }}</pre> -->

            <?php 
                // GETTING DATABASE DATA
                foreach($mapping_fields[0] as $key => $fields) { 

                    // IF data.name from api and the content key match
                    $sub_fields = array();
                    $field_key = '';
                    ?>
                    <# if ( '<?php echo $key; ?>' == data.name ) { #>
                        <?php 
                        
                        foreach($fields as $child_key => $child_fields) {
                            if($child_key == 'field') {
                                $field_key = $child_fields;
                            }
                            if($child_key == 'sub_fields') {
                                array_push($sub_fields,$child_fields);
                            }
                        }

                        // If there is a field field
                        if($field_key) {
                            echo '<div style="display:none;" class="field-key" data-set="{{data.name}}" data-key="' . $field_key . '">' . $field_key . '</div>';
                        }

                        // If there are sub-fields saved
                        if(!empty($sub_fields[0])) {
                            echo '<ul class="sub-fields" data-set="{{data.name}}" style="display:none">';
                                $index = 0;
                                foreach($sub_fields[0] as $sub_field) {
                                    echo '<li class="sub-field" id="sub-field-{{data.name}}-' . $index . '" data-value="' . $sub_field . '">' . $sub_field . '</li>';
                                    $index++;
                                }
                            echo '</ul>';
                        }
                    ?>
                <# } #>
            <?php } ?>

            <select id="field-group-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new wp-type-value-select field-select-group <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <option data-group="" data-set="" value="">Unused</option>
                 <# _.each( <?php echo json_encode($group_results); ?>, function( group ) { #>
                    <option data-group="{{group.post_name}}" <# if ( group.post_name === data.field_value ) { #>selected="selected"<# } #> data-set="{{data.field_value}}" value="{{ group.ID }}">{{ group.post_title }}</option>
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
        

        /******************* NEW IDEA ************************/

        // GET ACF FIELD GROUPS & SAVED DATA
        let field_groups = [];
        let saved_data = [];
        function acf_field_groups() {
            let template_id = $('.mapping-id').attr('data-template-id');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action : 'gc_get_fields_for_group', // RE-USING AJAX CALL
                    template_id : template_id,
                },
                success: function(response) {
                    saved_data.push(response.saved.mapping);
                    field_groups.push(response.field_groups);
                }
            });
        }
        setTimeout(function() {
            acf_field_groups();
            page_init();
        },300);

        function page_init() {
            $('.field-select-group').each(function() {
                let data_set = $(this).attr('data-set');
                let group_id = $(this).val();
                let field_group = 'group_' + group_id;
                let fields = field_groups.field_group;
                let field_select = $('#field-select-' + data_set);
                console.log(field_select);
            });
        }

        console.log(field_groups);
        console.log(saved_data);

        // ON PAGE LOAD CHECK IF THERE ARE FIELDS TO POPULATE

        // THEN SELECT THE ALREADY SELECT FIELDS

        // ON CLICK FUNCTIONS THAT PROVIDE CHANGES TO FIELD AND SUB FIELDS


        /********************************************/

        // Set saved ACF content
        // setTimeout(function() {
        //     group_type_change();
        //     group_change();
        //     field_change();
        // },100);

        // setTimeout(function() {
        //     set_field();
        // },300);

        // setTimeout(function() {
        //     field_group_check();
        // },700);
        
        // // Loads Sub Fields if available
        // $(window).on('ajaxComplete', function() {
        //     group_type_change();
        //     group_change();
        //     field_change();
        //     set_sub_fields();
        // });

        // function group_type_change() {
        //     $('.wp-type-select').on('change',function() {
        //         setTimeout(function() {
        //             group_change();
        //             field_change();
        //         },200);
        //     });
        // }


        // Set FIELD if saved
        function set_field() {
            $('.field-select').each(function() {
                let select_options = $(this).children('option');
                let field_set = $(this).attr('data-set');
                let field_key = '';
                $('.field-key').each(function() {
                    let field_key_set = $(this).attr('data-set');
                    let field_key_value = $(this).attr('data-key');
                    if(field_set == field_key_set) {
                        field_key = field_key_value;
                    }
                });
                if(field_key) {
                    $(select_options).each(function() {
                        let option_value = $(this).val();
                        if($(this).val() === field_key) {
                            $(this).attr('selected','selected');
                        } else {
                            $(this).attr('selected',null);
                        }
                    });
                }
            });
        }

        // Set SUB-FIELDS if saved
        function set_sub_fields() {
            $('.sub-fields').each(function() {
                let field_group = $(this).attr('data-set');
                let sub_fields = $(this).children('li');
                let index = 1;
                $(sub_fields).each(function() {
                    let sub_field_val = $(this).attr('data-value');
                    let child_selects = $('td[data-set="' + field_group + '"]' ).children('select[data-index="' + index + '"]');
                    let child_options = $(child_selects).children('option');
                    $(child_selects).each(function() {
                        let index_level = $(this).attr('data-index');
                        let child_options = $(this).children('option');
                        $(child_options).each(function() {
                            let option_value = $(this).val();
                            if($(this).val() === sub_field_val) {
                                $(this).attr('selected','selected');
                            } else {
                                $(this).attr('selected',null);
                            }
                        });
                    });
                    index++;
                });
            });
        }


        // On ACF Group Change
        function group_change() {
            $('.field-select-group').on('change',function() {
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
                            
                            // SET FIELDS
                            let field_select = $(this).siblings('.field-select');
                            let select_options = $(field_select).children('option');
                            let field_set = $(field_select).attr('data-set');
                            let field_key = '';
                            $('.field-key').each(function() {
                                let field_key_set = $(this).attr('data-set');
                                let field_key_value = $(this).attr('data-key');
                                if(field_set == field_key_set) {
                                    field_key = field_key_value;
                                }
                            });
                            if(field_key) {
                                $(select_options).each(function() {
                                    let option_value = $(this).val();
                                    if($(this).val() === field_key) {
                                        $(this).attr('selected','selected');
                                    } else {
                                        $(this).attr('selected',null);
                                    }
                                });
                            }

                            // GET SUB FIELDS
                            let field_val = $(this).val();
                            if(field_val){
                                let field_sib_val = $(this).siblings('.field-select').val();
                                let sub_fields = $(this).parent('.column').siblings('.column').find('.component-child');
                                setTimeout(function() {
                                    if(select_fields) {
                                        $.ajax({
                                            url: ajaxurl,
                                            type: 'POST',
                                            dataType: 'JSON',
                                            data: {
                                                action : 'gc_get_fields',
                                                field_parent : field_val
                                            },
                                            success: function(response) {
                                                console.log(response);
                                                let fields = response;
                                                let fieldSelect = sub_fields;
                                                fieldSelect.empty();
                                                $.each(fields, function(key, field) {
                                                    if(field.key == field_sib_val) {
                                                        fieldSelect.append($('<option></option>').attr('value', '').text('Unused'));
                                                        $.each(fields.sub_fields, function(key, field) {
                                                            fieldSelect.append($('<option></option>').attr('value', field.key).text(field.label));
                                                        });
                                                    }
                                                    
                                                });
                                            },
                                            error: function () {
                                                //console.log('AJAX Request Error:');
                                            }
                                        });
                                    }
                                },100);
                            }
                            
                        },
                        error: function () {
                            //console.log('AJAX Request Error:');
                        }
                    });
                }
            });
        }



        // On ACF Field Change
        function field_change() {
            $('.field-select').on('change',function() {
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
                    error: function () {
                        //console.log('AJAX Request Error:');
                    }
                });
            });
        }

        // Field Group Populate
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
                            sub_fields_populate();
                        },
                        error: function () {
                            //console.log('AJAX Request Error:');
                        }
                    }); 
                }
            });
        }
        
        // Repeater Sub Field Populate
        function sub_fields_populate() {
            $('.field-select').each(function() {
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
                    error: function () {
                        //console.log('AJAX Request Error:');
                    }
                });
            });
        }

    }); 
</script>