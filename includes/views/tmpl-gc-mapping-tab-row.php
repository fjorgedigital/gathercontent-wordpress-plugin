<?php /*******************************************
	Component: Wrapper table - Open
***********************************************/ ?>
<# if ( data.typeName === 'component' ) { #>
<td class="component-td-wrapper component-td" colspan="2">
	<table class="component-table-wrapper">
		<tr>
<# } #>


<?php /*******************************************
	Standard TD Items - Lvl 1
***********************************************/ ?>
<?php // LEFT COLUMN - GC DATA ?>
<td <# if (data.typeName === 'component') { #> class="gc-component-disabled column"<# } #>>
	<# if ( ( data.limit && data.limit_type ) || data.instructions || data.typeName ) { #>
		<?php // <pre>{{ JSON.stringify(data, null, 2) }}</pre> ?>
		<# if ( ( data.is_repeatable ) ) { #>
			<span class="dashicons dashicons-controls-repeat" title="Repeatable Field"></span>
		<# } #>
		<a title="<?php _ex( 'Click to show additional details', 'About the GatherContent object', 'gathercontent-import' ); ?>" href="#" class="gc-reveal-items <# if(data.component){ #>gc-reveal-items-component<# } #> dashicons-before dashicons-arrow-<# if ( data.expanded ) { #>down<# } else { #>right<# } #>"><strong>{{ data.label }} <small>{{ data.subtitle }}</small></strong></a>
		<ul class="gc-reveal-items-list <# if ( !data.expanded ) { #>hidden<# } #>">
			<# if ( data.typeName ) { #>
			<li><strong><?php _e( 'Type:', 'gathercontent-import' ); ?></strong> {{ data.typeName }}</li>
			<# } #>

			<# if ( data.limit && data.limit_type ) { #>
			<li><strong><?php _e( 'Limit:', 'gathercontent-import' ); ?></strong> {{ data.limit }} {{ data.limit_type }} </li>
			<# } #>

			<# if ( data.instructions ) { #>
			<li><strong><?php _e( 'Description:', 'gathercontent-import' ); ?></strong> {{ data.instructions }}</li>
			<# } #>
		</ul>
	<# } else { #>
		<strong>{{ data.label }}</strong>
	<# } #>
</td>

<?php // RIGHT COLUMN - WP DATA FIELDS ?>
<td <# if (data.typeName === 'component') { #> class="gc-component-disabled column"<# } #>>
	<# if ( data.type === 'text_rich' && data.is_repeatable ) { #>
        <select class="wp-type-select type-select" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][type]">
			<option <# if ( '' === data.field_type ) { #>selected="selected"<# } #> value=""><?php _e( 'Unused', 'gathercontent-import' ); ?></option>
			<?php do_action( 'gathercontent_field_type_option_underscore_template', $this ); ?>
			<!-- <option value="wp-type-acf"><?php // _e( 'ACF Field Groups', 'gathercontent-import' ); ?></option> <?php // Display ACF when a text field is repeatable ?> -->
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


<?php /*******************************************
	Component: Sub-Fields Row & Close Wrapper
***********************************************/ ?>
<# if ( data.component ) { index = 1; #>
		</tr>

		<?php // Component - Sub Fields Data ?>
		<tr class="gc-component-row hidden">
			<td class="component-td" colspan="2">

			<?php /** COMPONENT SUB-FIELDS: FROM GC **/ ?>
			<table class="component-table-inner">
				<# _.each(data.component.fields, function(field) {  #>
					<?php // <pre>{{ JSON.stringify(data.component.fields[index], null, 2) }}</pre> ?>
					<tr>
						<td class="">
							<# if ( field.metadata && field.metadata.repeatable && field.metadata.repeatable.isRepeatable ) { #>
								<span class="dashicons dashicons-controls-repeat" title="Repeatable Field"></span>
							<# } #>
							<a title="<?php _ex( 'Click to show additional details', 'About the GatherContent object', 'gathercontent-import' ); ?>" href="#" class="gc-reveal-items dashicons-before dashicons-arrow-<# if ( data.expanded ) { #>down<# } else { #>right<# } #>"><strong>{{ field.label }} <small>{{ field.subtitle }}</small></strong></a>
							<ul class="gc-reveal-items-list gc-reveal-items-hidden <# if ( ! data.expanded ) { #>hidden<# } #>">	
								<# if(( field.field_type )){ #> <li><strong><?php _e( 'Type:', 'gathercontent-import' ); ?></strong> {{ field.field_type }}</li> <# } #>
								<# if(( field.instructions )){ #> <li><strong><?php _e( 'Instructions:', 'gathercontent-import' ); ?></strong> {{ field.instructions }}</li> <# } #>
							</ul>
						</td>

						<?php /** COMPONENT SUB-FIELDS: WP SELECTs **/ ?>
						<td class="acf-components" data-set="{{ data.name }}">
							<# if ( field.component ) { #>
								<select class="" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][type]">
									<option value="Component"><?php _e( 'Component', 'gathercontent-import' ); ?></option>
								</select>
							<# } else { #>
								<select id="component-child-{{ data.name }}-{{ index }}" data-set="{{ data.name }}" class="component-child" data-index="{{index}}" name="<?php $this->output( 'option_base' ); ?>[mapping][{{ data.name }}][sub_fields][{{index}}]">
									<option <# if ( '' === data.field_type ) { #>selected="selected"<# } #> value=""><?php _e( 'Unused', 'gathercontent-import' ); ?></option>
									<?php // do_action( 'gathercontent_field_type_option_underscore_template', $this ); ?>
								</select>
							<# } #>
						</td>
					</tr>
				<# index = index + 1; }); #>
			</table>
			</td>

		<?php // Component - Wrapper table: Close ?>
		</tr>
	</table>
</td>
<# } #>