<?php
/**
 * GatherContent Plugin
 *
 * @package GatherContent Plugin
 */

namespace GatherContent\Importer\Sync;

use GatherContent\Importer\Post_Types\Template_Mappings;
use GatherContent\Importer\Mapping_Post;
use GatherContent\Importer\API;
use WP_Error;

/**
 * Handles pushing content to GC.
 *
 * @since 3.0.0
 */
class Push extends Base {

	/**
	 * Sync direction.
	 *
	 * @var string
	 */
	protected $direction = 'push';

	/**
	 * Post object to push.
	 *
	 * @var int
	 */
	protected $post = null;

	/**
	 * Array of field types completed.
	 *
	 * @var array
	 */
	protected $done = array();

	/**
	 * A json-encoded reference to the original Item config object,
	 * before transformation for the update.
	 *
	 * @var string
	 */
	protected $config      = array();
	protected $item_config = array();

	private $item_id = null;

	/**
	 * Creates an instance of this class.
	 *
	 * @since 3.0.0
	 *
	 * @param API $api API object.
	 */
	public function __construct( API $api ) {
		parent::__construct( $api, new Async_Push_Action() );
	}

	/**
	 * Initiate admin hooks
	 *
	 * @since  3.0.0
	 *
	 * @return void
	 */
	public function init_hooks() {
		parent::init_hooks();
		add_action( 'wp_async_gc_push_items', array( $this, 'sync_items' ) );
		add_action( 'wp_async_nopriv_gc_push_items', array( $this, 'sync_items' ) );
	}

	/**
	 * A method for trying to push directly (without async hooks).
	 *
	 * @since  3.0.0
	 *
	 * @param  int $mapping_post_id Mapping post ID.
	 *
	 * @return mixed Result of push. WP_Error on failure.
	 */
	public function maybe_push_item( $mapping_post_id ) {
		try {

			$post       = $this->get_post( $mapping_post_id );
			$mapping_id = \GatherContent\Importer\get_post_mapping_id( $post->ID );

			$this->mapping = Mapping_Post::get( $mapping_id, true );

			$result = $this->do_item( $post->ID );

		} catch ( \Exception $e ) {
			$result = new WP_Error( 'gc_push_item_fail_' . $e->getCode(), $e->getMessage(), $e->get_data() );
		}

		return $result;
	}

	/**
	 * Pushes WP post to GC after some sanitiy checks.
	 *
	 * @since  3.0.0
	 *
	 * @param  int $id WP post ID.
	 *
	 * @throws Exception On failure.
	 *
	 * @return mixed Result of push.
	 */
	protected function do_item( $id ) {

		$this->post = $this->get_post( $id );

		$this->check_mapping_data( $this->mapping );

		$this->set_item( \GatherContent\Importer\get_post_item_id( $this->post->ID ), true );

		$config_update = $this->map_wp_data_to_gc_data();
		// error_log(print_r($config_update, true));

		// No updated data, so bail.
		if ( empty( $config_update ) ) {

			throw new Exception(
				sprintf( __( 'No update data found for that post ID: %d', 'gathercontent-import' ), $this->post->ID ),
				__LINE__,
				array(
					'post_id'    => $this->post->ID,
					'mapping_id' => $this->mapping->ID,
					'item_id'    => $this->item->id ?? 0,
				)
			);
		}

		// If we found updates, do the update.
		return $this->maybe_do_item_update( $config_update );
	}

	/**
	 * Pushes WP post to GC.
	 *
	 * @since  3.0.0
	 *
	 * @param  array $update The item config update delta array.
	 *
	 * @throws Exception On failure.
	 *
	 * @return mixed Result of push.
	 */
	public function maybe_do_item_update( $update ) {
		// error_log(print_r($update, true));
		// Get our initial croonfig reference.
		$config = json_decode( $this->config );

		// And update the content with the new values.
		foreach ( $update as $updated_element ) {
			// error_log(print_r($updated_element, true));
			$element_id = $updated_element->name;

			// handle repeatable elements because we stored them in JSON format earlier and GC requires it in array format
			if ( $updated_element->repeatable ) {

				// $repeatable_value = ! empty( $updated_element->value ) ? @json_decode( $updated_element->value, true ) : $updated_element->value;
				if (is_string($updated_element->value)) {
					$repeatable_value = !empty( $updated_element->value ) ? json_decode( $updated_element->value, true) : $updated_element->value;
				} else {
					// Handle the case where $updated_element->value is already an array
					$repeatable_value = $updated_element->value;
				}
				if ( is_array( $repeatable_value ) ) {
					$updated_element->value = $repeatable_value;
				} else {
					$updated_element->value = array();
				}
			}

			// handle new item because we don't have content object for it
			if ( ! isset( $config->content ) ) {
				$config->content = (object) array();
			}

			// finally push it to the content array if the data was changed
			if ( $component_uuid = $updated_element->component_uuid ) {

				if ( ! isset( $config->content->$component_uuid ) ) {
					$config->content->$component_uuid = (object) array();
				}

				if(is_array($config->content->$component_uuid) && is_array(json_decode($updated_element->value))){
					// it's a repeatable component so handle differently
					$decoded_value = json_decode($updated_element->value);
					$i = 0;
					foreach($decoded_value as $value) {
						if(isset($config->content->$component_uuid[$i])){
							$config->content->$component_uuid[$i]->$element_id = $value;
						}
						$i++;
					}
				} else {
					$config->content->$component_uuid->$element_id = $updated_element->value;
				}

			} else {
				$config->content->$element_id = $updated_element->value;
			}
		}

		if ( $this->item_id ) {
			$result = $this->api->uncached()->update_item( $this->item_id, $config );
		} else {
			$result = $this->api->create_item(
				$this->mapping->get_project(),
				$this->mapping->get_template(),
				$this->post->post_title,
				$config->content
			);
		}

		// todo: figure out the structure_uuid scenario which I removed from the old code, because there's no way that scenario can regenerated (@ shehrozsheikh@zao [2021-25-11])

		if ( $result && ! is_wp_error( $result ) ) {
			if ( ! $this->item_id ) {
				\GatherContent\Importer\update_post_item_id( $this->post->ID, $result );
				$this->item_id = $result;
			}

			// If item update was successful, re-fetch it from the API...
			$this->item = $this->api->uncached()->get_item( $this->item_id, true );

			// and update the meta.
			\GatherContent\Importer\update_post_item_meta(
				$this->post->ID,
				array(
					'created_at' => $this->item->created_at,
					'updated_at' => $this->item->updated_at,
				)
			);
		}

		return $result;
	}

	/**
	 * Sets the item to be pushed to. If it doesn't exist yet, we create it now.
	 *
	 * @since 3.0.0
	 *
	 * @param integer $item_id Item id.
	 * @param  bool    $exclude_status set this to true to avoid appending status data
	 *
	 * @throws Exception On failure.
	 *
	 * @return $item
	 */
	protected function set_item( $item_id, $exclude_status = false ) {
		$this->item_id = $item_id;

		if ( ! $item_id ) {
			$item = $this->api->get_template( $this->mapping->get_template() );
		} else {
			$item = parent::set_item( $item_id, $exclude_status );
		}

		$this->item_config = $item;
		$this->item        = $item;

		// storing it to compare the changed data later
		$this->config = wp_json_encode( $item );

	}

	/**
	 * Maps the WP post data to the GC item config.
	 *
	 * @since  3.0.0
	 *
	 * @return array Item config array on success.
	 */
	protected function map_wp_data_to_gc_data() {
		$config = $this->loop_item_elements_and_map();
		// error_log(print_r($this, true));
		return apply_filters( 'gc_update_gc_config_data', $config, $this );
	}

	/**
	 * Loops the GC item config elements and maps the WP post data.
	 *
	 * @since  3.0.0
	 *
	 * @return array Modified item config array on success.
	 */
	public function loop_item_elements_and_map() {
		if ( empty( $this->item_config ) ) {
			return false;
		}

		$structure_groups = isset( $this->item_config->related ) ? $this->item_config->related->structure->groups : $this->item_config->structure->groups;
		
		$this->item_config = array();
		
		if ( ! isset( $structure_groups ) || empty( $structure_groups ) ) {
			return false;
		}

		// to handle multiple tabs
		foreach ( $structure_groups as $index => $tab ) {
			if ( ! isset( $tab->fields ) || ! $tab->fields ) {
				continue;
			}
			
			// to handle fields in a tab
			foreach ( $tab->fields as $element_index => $field ) {

				// to handle components with multiple fields inside
				$fields_data    = $field->component->fields ?? array( $field );
				$component_uuid = 'component' === $field->field_type ? $field->uuid : '';
				
				$is_component_repeatable = false;
				if($component_uuid) {
					$metadata      = $field->metadata;
					$is_component_repeatable = ( is_object( $metadata ) && isset( $metadata->repeatable ) ) ? $metadata->repeatable->isRepeatable : false;
				}
				error_log(print_r($fields_data, true));
				$componentProcessed = false;
				foreach ( $fields_data as $field_data ) {

					$this->element = (object) $this->format_element_data( $field_data, $component_uuid, false, $is_component_repeatable );
					
					if ( $component_uuid ) {
						$this->element->component_uuid = $component_uuid;
					}
					// error_log(print_r($field_data, true));
					$uuid = $this->element->name;
					if ( $component_uuid && !$componentProcessed) {
						$this->element->component_uuid = $component_uuid;
						$uuid = $component_uuid . '_component_' . $component_uuid;
						$componentProcessed = true;
					}
					// error_log(print_r($this->item_config, true));
					$source      = $this->mapping->data( $uuid );
					$source_type = isset( $source['type'] ) ? $source['type'] : '';
					
					// Check if $source['field'] exists, then use it as the key
					if (isset($source['field'])) {
						// not sure if the field can be empty, will need to check that later on
						$source_key = $source['field'];
					} else {
						// If $source['field'] doesn't exist, fall back to using $source['value']
						$source_key = isset($source['value']) ? $source['value'] : '';
					}


					if ( $source_type ) {
						if ( ! isset( $this->done[ $source_type ] ) ) {
							$this->done[ $source_type ] = array();
						}

						if ( ! isset( $this->done[ $source_type ][ $source_key ] ) ) {
							$this->done[ $source_type ][ $source_key ] = array();
						}

						$this->done[ $source_type ][ $source_key ][ $index . ':' . $element_index ] = (array) $this->element;
					}
					
					if (
						$source
						&& isset( $source['type'], $source['value'] )
						&& $this->set_values_from_wp( $source_type, $source_key )
					) {
						$this->item_config[] = $this->element;
						// error_log(print_r($source, true));
					}
				}
			}
		}
		error_log(print_r($this->item_config, true));////
		$this->remove_unknowns();
		
		return $this->item_config;
	}

	/**
	 * Loops the $done array and looks for duplicates (unknowns) and removes them.
	 *
	 * @todo Fix this. Probably need a reverse mapping UI for each item push, or something.
	 *
	 * @since  3.0.0
	 *
	 * @return void
	 */
	protected function remove_unknowns() {
		foreach ( $this->done as $source_type => $keys ) {
			foreach ( $keys as $source_key => $values ) {
				if ( count( $values ) < 2 ) {
					// We're good to go!
					continue;
				}

				/*
				 * @todo fix this.
				 * UH OH, this means we've encountered some appendable field types which
				 * have more than one GC value mapping to them. We don't have a reliable
				 * way of parsing those bits back to the individual GC fields, So we have
				 * to simply remove them from the update.
				 */

				foreach ( $values as $key => $value ) {
					$keys = explode( ':', $key );

					if ( isset( $this->item_config[ $keys[0] ]->elements[ $keys[1] ] ) ) {
						unset( $this->item_config[ $keys[0] ]->elements[ $keys[1] ] );
					}

					if ( empty( $this->item_config[ $keys[0] ]->elements ) ) {
						unset( $this->item_config[ $keys[0] ] );
					}
				}
			}
		}
	}

	/**
	 * Sets the item config element value, if it is determeined that the value changed.
	 *
	 * @since 3.0.0
	 *
	 * @param string $source_type The data source type.
	 * @param string $source_key  The data source key.
	 *
	 * @return array $updated Whether value was updated.
	 */
	protected function set_values_from_wp( $source_type, $source_key ) {
		$updated = false;
		// error_log("*****Source Type: $source_type ******** Source Key: $source_key *****");
		switch ( $source_type ) {
			case 'wp-type-post':
				$updated = $this->set_post_field_value( $source_key );
				break;

			case 'wp-type-taxonomy':
				$updated = $this->set_taxonomy_field_value( $source_key );
				break;

			case 'wp-type-meta':
				$updated = $this->set_meta_field_value( $source_key );
				break;

			case 'wp-type-media':
				$this->set_featured_image_alt( $source_key );
				break;

			case 'wp-type-acf':
				$updated = $this->set_acf_field_value( $source_key );
				break;

		}

		return $updated;
	}


	/**
	 * Updates the featured image alt_text if changed
	 *
	 * @since 3.2.0
	 *
	 * @param string $source_key source key.
	 *
	 * @return void
	 */
	protected function set_featured_image_alt( $source_key ) {

		if ( 'featured_image' !== $source_key ) {
			return;
		}

		$attach_id = get_post_thumbnail_id( $this->post->ID );

		if ( ! $attach_id ) {
			return;
		}

		if ( $meta = \GatherContent\Importer\get_post_item_meta( $attach_id ) ) {

			$old_alt_text     = $meta['alt_text'] ?? '';
			$updated_alt_text = get_post_meta( $attach_id, '_wp_attachment_image_alt', true );

			if ( $old_alt_text !== $updated_alt_text && isset( $meta['file_id'] ) ) {

				$meta['alt_text'] = $updated_alt_text ?? '';

				if ( empty( $meta['alt_text'] ) ) {
					return;
				}

				$result = $this->api->update_file_meta(
					$this->mapping->get_project(),
					$meta['file_id'],
					array(
						'alt_text' => $meta['alt_text'],
					)
				);

				if ( ! $result ) {
					return;
				}

				// update the new alt_text in the attachment meta
				\GatherContent\Importer\update_post_item_meta(
					$attach_id,
					$meta
				);

			}
		}
	}


	/**
	 * Sets the item config element value for WP post fields,
	 * if it is determeined that the value changed.
	 *
	 * @since 3.0.0
	 *
	 * @param string $post_column The post data column.
	 *
	 * @return bool $updated Whether value was updated.
	 */
	protected function set_post_field_value( $post_column ) {
		$updated  = false;
		$el_value = $this->element->value; 
		// error_log(print_r($this, true));
		$value = ! empty( $this->post->{$post_column} ) ? self::remove_zero_width( $this->post->{$post_column} ) : false;
		$value = apply_filters( "gc_get_{$post_column}", $value, $this );

		// Make element value match the WP versions formatting, to see if they are equal.
		switch ( $post_column ) {
			case 'post_title':
				$el_value = wp_kses_post( $this->get_element_value() );
				break;
			case 'post_content':
			case 'post_excerpt':
				$el_value = wp_kses_post( $this->get_element_value() );
				if ( 'post_content' === $post_column ) {
					$value = apply_filters( 'the_content', $value );
				}

				// There are super minor encoding issues we want to ignore.
				similar_text( $value, $el_value, $percent_similarity );
				if ( $percent_similarity > 99.9 ) {
					$value = $el_value;
				}
				break;
		}
		// @codingStandardsIgnoreStart
		// We don't necessarily want strict comparison here.
		if ( $value != $el_value ) {
			// @codingStandardsIgnoreEnd
			$this->element->value = $value;
			$updated              = true;
		}

		return $updated;
	}

	/**
	 * Sets the item config element value for WP taxonomy terms,
	 * if it is determeined that the value changed.
	 *
	 * @since 3.0.0
	 *
	 * @param string $taxonomy The taxonomy name.
	 *
	 * @return bool $updated Whether value was updated.
	 */
	protected function set_taxonomy_field_value( $taxonomy ) {
		$terms      = get_the_terms( $this->post, $taxonomy );
		$term_names = ! is_wp_error( $terms ) && ! empty( $terms )
			? wp_list_pluck( $terms, 'name' )
			: array();

		$updated = $this->set_taxonomy_field_value_from_names( $term_names );

		return apply_filters( 'gc_config_taxonomy_field_value_updated', $updated, $taxonomy, $terms, $this );
	}

	public function set_taxonomy_field_value_from_names( $term_names ) {
		$updated = false;

		switch ( $this->element->type ) {

			case 'text':
				$item_vals = array_map( 'trim', explode( ',', $this->element->value ) );

				$diff = array_diff( $term_names, $item_vals );
				if ( empty( $diff ) ) {
					$diff = array_diff( $item_vals, $term_names );
				}

				if ( ! empty( $diff ) ) {
					$this->element->value = ! empty( $term_names ) ? implode( ', ', $term_names ) : '';
					$updated              = true;
				}
				break;

			case 'choice_checkbox':
			case 'choice_radio':
				$updated = $this->update_element_selected_options(
					function( $label ) use ( $term_names ) {
						return in_array( $label, $term_names, true );
					}
				);

				// @codingStandardsIgnoreStart
				/*
				 * Probably can't create options via the API.
				 *
				 * @todo we'll leave this for the future, in case you can.
				 *
				 * $option_names = wp_list_pluck( $this->element->options, 'label' );
				 * $new_terms = array_diff( $term_names, $option_names );
				 * foreach ( $new_terms as $new_term ) {
				 * 	$this->element->options[] = (object) array(
				 * 		'label' => $new_term,
				 * 		'selected' => true,
				 * 	)
				 * }
				 */
				// @codingStandardsIgnoreEnd
				break;

		}

		return $updated;
	}

	/**
	 * Sets the item config element value for WP meta fields,
	 * if it is determeined that the value changed.
	 *
	 * @since 3.0.0
	 *
	 * @param string $meta_key The meta key.
	 *
	 * @return bool $updated Whether value was updated.
	 */
	protected function set_meta_field_value( $meta_key ) {
		$updated    = false;
		$meta_value = get_post_meta( $this->post->ID, $meta_key, 1 );
		
		$check = apply_filters( 'gc_config_pre_meta_field_value_updated', null, $meta_value, $meta_key, $this );
		if ( null !== $check ) {
			return $check;
		}

		switch ( $this->element->type ) {

			case 'text':
				// @codingStandardsIgnoreStart
				// We don't necessarily want strict comparison here.
				if ( $meta_value != $this->element->value ) {
					// @codingStandardsIgnoreEnd
					$this->element->value = $meta_value;
					$updated              = true;
				}
				break;

			case 'choice_radio':
				$updated = $this->update_element_selected_options(
					function( $label ) use ( $meta_value ) {
						return $meta_value === $label;
					}
				);
				break;

			case 'choice_checkbox':
				if ( empty( $meta_value ) ) {
					$meta_value = array();
				} else {
					$meta_value = is_array( $meta_value ) ? $meta_value : array( $meta_value );
				}

				$updated = $this->update_element_selected_options(
					function( $label ) use ( $meta_value ) {
						return in_array( $label, $meta_value, true );
					}
				);
				break;

		}

		return apply_filters( 'gc_config_meta_field_value_updated', $updated, $meta_value, $meta_key, $this );
	}


	/**
	* Sets the item config element value for ACF fields,
	* if it is determeined that the value changed.
	*
	* @since 3.0.0
	*
	* @param  string $group_key  The ACF group key.
	* @param  string $field_key  The ACF field key.
	* @param  array  $post_data  The WP Post data array.
	*
	* @return bool $updated Whether value was updated.  
	*/

	protected function set_acf_field_value($group_key) {
		// Always return true
		$updated = true;
	
		// Get the post ID
		$post_id = $this->post->ID;
	
		// Fetch the ACF field group using the group key
		$field_group = get_field($group_key, $post_id);
		// error_log(print_r($field_group, true));
		// error_log(print_r($this->item->content, true));

		$el = $this->element;
		if (is_object($el) && property_exists($el, 'component_uuid')) {
			// We have a component here
			$structure_groups = $this->item->structure->groups;
			$componentFieldsKeys = [];
			// error_log(print_r($structure_groups, true));
			foreach ($structure_groups as $group) {
				$fields = $group->fields;
				foreach ($fields as $field) {
					if ($field->uuid == $el->component_uuid){
						$component = $field->component;
						// Check if the component property exists and is an object
						if (is_object($component) && property_exists($component, 'fields')) {
							// Access the fields property of the component object
							$componentFields = $component->fields;
							// error_log(print_r($componentFields, true));
							foreach ($componentFields as $componentField) {
								$componentFieldsKeys[]= $componentField->uuid;
								$fieldType = $componentField->field_type;
								
							}
						}
					}
				}
			}

			$groupData = [];
			foreach ($field_group as $group) {
				// Combine keys from componentFieldsKeys with values from the current group
				$new_group = array_combine($componentFieldsKeys, $group);
				$groupData[] = $new_group;
			}
			$outputArray = [];
			$outputArrayArray = [];
			$outputArrayText = [];
			foreach ($groupData as $key => $dataInstance) {
				foreach ($dataInstance as $field_uuid => $field_value) {
					// Find the field in $componentFields using its UUID as the key
					$field_type = null;
					
					foreach ($componentFields as $componentField) {
						if ($componentField->uuid === $field_uuid) {
							$field_type = $componentField->field_type;
							break; // Stop iterating once the field with the matching UUID is found
						}
					}
					
					// Start switch case for each field type
					switch ($field_type) {
						case 'text':
							// Handle text field type
							if (is_array($field_value)) {
								// $outputArrayArray = [];
								// If the field value is an array, encode its elements separately
								foreach ($field_value as $item) {
									$jsonValue = $this->textFieldToJSON($item);
									$encodedValue = [];
									foreach ($item as $itemVal) {
										$encodedValue[] = json_encode($itemVal); // Encode each array element directly
									}
									// Implode the encoded elements to form a single string
									$encodedValue = implode(',', $encodedValue);
									if ($jsonValue !== null) {
										$outputArrayArray[] = $jsonValue;
									}
								}
							} else {
								// $outputArrayText = [];
								// If the field value is not an array, encode it directly
								// $jsonValue = $this->textFieldToJSON($field_value);
								$jsonValue = json_encode($field_value);
								if ($jsonValue !== null) {
									$outputArrayText[] = $jsonValue;
								}
							}
							
							break;
						case 'attachment':
							// Handle attachment field type
							break;
						case 'choice_checkbox':
							// Handle choice checkbox field type
							// error_log(print_r($field_value, true));
							break;
						case 'choice_radio':
							// Handle choice radio field type
							break;
						default:
							// Default case if field type doesn't match any known cases
							break;
					}
					
				}
			}

			// if ($outputArray){
			// 	$result = '[' . implode(',', $outputArray) . ']';
			// 	$this->element->value = $result;
			// 	$this->item_config[] = $this->element;
			// }
			// if ($outputArrayArray){
			// 	$result = '[' . implode(',', $outputArrayArray) . ']';
			// 	$this->element->value = $result;
			// 	$this->item_config[] = $this->element;
			// }
			if ($outputArrayText){
				$result = '[' . implode(',', $outputArrayText) . ']';
				$this->element->value = $result;
				$this->item_config[] = $this->element;
			}
			
			
		} else {
			$outputArray = array();
			foreach ($field_group as $item) {
				// Get the values of the sub-array dynamically
				$values = array_values($item);
				// Use json_encode to encode the value
				$outputArray[] = json_encode($values[0]);
			}

			$result = '[' . implode(',', $outputArray) . ']';

			$this->element->value = $result;
			// $this->item_config[] = $this->element;
		}
		
		// error_log(print_r($this->item_config, true));

		$updated = true;
		return $updated;
	}
	


	/**
	 * Convert a value to JSON format, handling arrays accordingly
	 * @param mixed $value The value to convert to JSON
	 * @return string The JSON-encoded value
	 */
	protected function textFieldToJSON($value) {
		if (is_array($value)) {
			// If the value is an array, encode its elements separately
			$encodedValue = [];
			foreach ($value as $item) {
				$encodedValue[] = json_encode($item); // Encode each array element directly
			}
			// Implode the encoded elements to form a single string
			$encodedValue = implode(',', $encodedValue);
		} else {
			// If the value is not an array, encode it directly
			$encodedValue = json_encode($value);
		}
		return $encodedValue;
	}


	/**
	 * Transforms the value of an ACF field.
	 * 
	 * @param mixed $field The ACF field value to transform.
	 *
	 * @return mixed The transformed ACF field value.
	 */
	protected function transform_acf_field_value($field) {
		// Lets implement transformation logic here
		// error_log(print_r($field, true));
		return $field;
	}
	

	/**
	 * Uses $callback to determine if each option value should be selected,
	 *
	 * @since  3.0.0
	 *
	 * @param  callable $callback Closure.
	 *
	 * @return bool             Whether the options were updated or not.
	 */
	public function update_element_selected_options( $callback ) {
		$pre_options = wp_json_encode( $this->element->options );

		$last_key = false;
		if ( isset( $this->element->other_option ) && $this->element->other_option ) {
			$keys     = array_keys( $this->element->options );
			$last_key = end( $keys );
		}

		foreach ( $this->element->options as $key => $option ) {

			// If it's the "Other" option, we need to use the option's value, not label.
			$label = $last_key === $key && isset( $option->value )
				? $option->value
				: $option->label;

			if ( $callback( self::remove_zero_width( $label ) ) ) {
				$this->element->options[ $key ]->selected = true;
			} else {
				$this->element->options[ $key ]->selected = false;

				// Else GC API error:
				// "Other option value must be empty when other option not selected".
				if ( $last_key === $key ) {
					$this->element->options[ $key ]->value = '';
				}
			}
		}

		$post_options = wp_json_encode( $this->element->options );

		// @codingStandardsIgnoreStart
		// Check if the values have been updated.
		// We don't necessarily want strict comparison here.
		return $pre_options != $post_options;
		// @codingStandardsIgnoreEnd
	}

}
