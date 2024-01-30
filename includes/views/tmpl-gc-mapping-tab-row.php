<td <# if (data.typeName === 'component') { #> class="gc-component-disabled column"<# } #>>
	<# if ( ( data.limit && data.limit_type ) || data.instructions || data.typeName ) { #>
	<# if ( ( data.is_repeatable ) ) { #>
		<span class="dashicons dashicons-controls-repeat" title="Repeatable Field"></span>
	<# } #>
	<a title="<?php _ex( 'Click to show additional details', 'About the GatherContent object', 'gathercontent-import' ); ?>" href="#" class="gc-reveal-items dashicons-before dashicons-arrow-<# if ( data.expanded ) { #>down<# } else { #>right<# } #>"><strong>{{ data.label }} <small>{{ data.subtitle }}</small></strong></a>
	<ul class="gc-reveal-items-list <# if ( ! data.expanded ) { #>hidden<# } #>">	
		<# if ( data.typeName ) { #>
		<li><strong><?php _e( 'Type:', 'gathercontent-import' ); ?></strong> {{ data.typeName }}</li>
		<# } #>

		<# if ( data.limit && data.limit_type ) { #>
		<li><strong><?php _e( 'Limit:', 'gathercontent-import' ); ?></strong> {{ data.limit }} {{ data.limit_type }} </li>
		<# } #>

		<# if ( data.instructions ) { #>
		<li><strong><?php _e( 'Description:', 'gathercontent-import' ); ?></strong> {{ data.instructions }}</li>
		<# } #>

		<# if ( data.component ) { index = 1; #>
			<table>
				<# _.each(data.component.fields, function(field) {  #>
					
					<tr>
						<td>
							<# if ( field.metadata && field.metadata.repeatable && field.metadata.repeatable.isRepeatable ) { #>
								<span class="dashicons dashicons-controls-repeat" title="Repeatable Field"></span>
							<# } #>
							<a title="<?php _ex( 'Click to show additional details', 'About the GatherContent object', 'gathercontent-import' ); ?>" href="#" class="gc-reveal-items dashicons-before dashicons-arrow-<# if ( data.expanded ) { #>down<# } else { #>right<# } #>"><strong>{{ field.label }} <small>{{ field.subtitle }}</small></strong></a>
							<ul class="gc-reveal-items-list <# if ( ! data.expanded ) { #>hidden<# } #>">	
								<li><strong><?php _e( 'Type:', 'gathercontent-import' ); ?></strong> {{ field.field_type }}</li>
								<li><strong><?php _e( 'Instructions:', 'gathercontent-import' ); ?></strong> {{ field.instructions }}</li>
							</ul>
						</td>
						<td class="acf-components" data-set="{{ data.name }}">
							<# if ( field.component ) { #>
								<select class="wp-type-value-select" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][type]">
									<option value="Component"><?php _e( 'Component', 'gathercontent-import' ); ?></option>
								</select>
							<# } else { #>
								<select id="component-child-{{ data.name }}-{{ index }}" data-set="{{ data.name }}" class="component-child" data-index="{{index}}" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][sub_fields][{{index}}]">
									<option <# if ( '' === data.field_type ) { #>selected="selected"<# } #> value=""><?php _e( 'Unused', 'gathercontent-import' ); ?></option>
									<?php do_action( 'gathercontent_field_type_option_underscore_template', $this ); ?>
								</select>
							<# } #>
						</td>
					</tr>
					<!-- <pre>{{ JSON.stringify(data.component.fields[index], null, 2) }}</pre> -->
				<# index = index + 1; }); #>
			</table>
		<# } #>
	</ul>
	<# } else { #>
	<strong>{{ data.label }}</strong>
	<# } #>
</td>
<td <# if (data.typeName === 'component') { #> class="gc-component-disabled column"<# } #>>
    <# if ( data.component ) { #>
        <select class="wp-type-select type-select" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][type]">
			<option <# if ( '' === data.field_type ) { #>selected="selected"<# } #> value=""><?php _e( 'Unused', 'gathercontent-import' ); ?></option>
			<?php do_action( 'gathercontent_field_type_option_underscore_template', $this ); ?>
        </select>
		<?php do_action( 'gathercontent_field_type_underscore_template', $this ); ?>
    <# } else { #>
        <select class="wp-type-select type-select" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][type]">
            <option <# if ( '' === data.field_type ) { #>selected="selected"<# } #> value=""><?php _e( 'Unused', 'gathercontent-import' ); ?></option>
            <?php do_action( 'gathercontent_field_type_option_underscore_template', $this ); ?>
        </select>
        <?php do_action( 'gathercontent_field_type_underscore_template', $this ); ?>
    <# } #>
</td>