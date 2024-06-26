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
        $options_acf_groups = array();
        $options_acf_groups_fields = array();

        // ============= BUILD FIELD GROUP OPTIONS =============
        $groups_query = "SELECT * FROM wp_posts WHERE post_type = 'acf-field-group' AND post_status = 'publish' AND post_parent = 0";
        $group_results = $wpdb->get_results($groups_query);
        
        // FIELD GROUPS
        if($group_results){
            // Extract group IDs
            $groupIds = array_map(function ($group) use ($wpdb) {
                return $wpdb->prepare('%d', $group->ID);
            }, $group_results);
        
            $groupIds = implode(',', $groupIds);
        
            // Prepare and execute query to get all fields for all groups
            $fields_query = "SELECT * FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_content LIKE '%repeater%' AND post_parent IN ($groupIds)";
            $fields_results = $wpdb->get_results($fields_query);
        
            // Group the field results by parent group
            $grouped_field_results = [];
            foreach ($fields_results as $field) {
                $grouped_field_results[$field->post_parent][] = $field;
            }
        
            foreach($group_results as $group) {
                // Set the top level field group array of options
                $options_acf_groups[$group->post_name] = $group->post_title;
                // Create a blank array based on the group id to define the groups fields
                $options_acf_groups_fields[$group->post_name] = array();
        
                // Get the fields for the current group from grouped results
                $fields_for_group = $grouped_field_results[$group->ID] ?? [];
        
                // Loop fields within each ACF Field Group
                foreach($fields_for_group as $field) {
                    // Build array of fields based on parent group
                    $options_acf_groups_fields[$group->post_name][$field->post_name] = $field->post_title;
                }
            }
        }
        // print_r($options_acf_groups);
        // print_r($options_acf_groups_fields);
        ?>

        <# if ( '<?php $this->e_type_id(); ?>' === data.field_type ) { #>

            <select id="field-group-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-value-select gc-select2 gc-select2-add-new field-select-group <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][value]">
                <option data-group="" data-set="" value="">Unused</option>
                <?php $this->underscore_options( $options_acf_groups ); ?>
                <?php $this->underscore_empty_option( __( 'Do Not Import', 'gathercontent-import' ) ); ?>
            </select>
            <span style="display: block; margin: 5px 0;"></span>
            <select id="field-select-{{data.name}}" data-set="{{data.name}}" class="wp-type-field-select gc-select2 gc-select2-add-new field-select-field <?php $this->e_type_id(); ?>" name="<?php $view->output( 'option_base' ); ?>[mapping][{{ data.name }}][field]">
                <?php /**
                 * If first field has value > populate options from that field group
                 * If field_field, mark selected
                 */ ?>
                <# if ( data.field_value ) { #>
                    <?php foreach($options_acf_groups_fields as $group_id => $fields ): ?>
                        <# if ( '<?php echo $group_id; ?>' === data.field_value ) { #>
                            <option value=""><?php _e( 'Unused', 'gathercontent-import' ); ?></option>
                            <?php foreach($fields as $field_id => $field_name ):
                                echo '<option <# if ( "' . $field_id . '" === data.field_field ) { #>selected="selected"<# } #> value="' . $field_id . '">' . $field_name . '</option>';
                            endforeach; ?>
                        <# } #>
                    <?php endforeach; ?>
                <# } #>
            </select>
            
        <# } #>
<?php } } ?>
