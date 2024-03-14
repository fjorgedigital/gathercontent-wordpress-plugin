module.exports = function( app, _meta_keys ) {
	return app.views.base.extend({
		tagName : 'tr',
		template : wp.template( 'gc-mapping-tab-row' ),

		events : {
			'change .wp-type-select'       : 'changeType',
			'change .wp-type-value-select' : 'changeValue',
			'change .wp-type-field-select' : 'changeField',
			'change .wp-subfield-select' : 'changeSubfield',
			'click  .gc-reveal-items'      : 'toggleExpanded'
		},

		initialize: function() {
			this.listenTo( this.model, 'change:field_type', this.render );
			console.log("this.model: ",this.model);

			// Initiate the metaKeys collection.
			this.metaKeys = new ( app.collections.base.extend( {
				model : app.models.base.extend( { defaults: {
					value : '',
					field : '',
					subfields : '',
				} } ),
				getByValue : function( value ) {
					return this.find( function( model ) {
						return model.get( 'value' ) === value;
					} );
				},
				getByField : function( field ) {
					return this.find( function( model ) {
						return model.get( 'field' ) === field;
					} );
				},
				getBySubfields : function( subfields ) {
					return this.find( function( model ) {
						return model.get( 'subfields' ) === subfields;
					} );
				},
			} ) )( _meta_keys );
		},

		changeType: function( evt ) {
			this.model.set( 'field_type', jQuery( evt.target ).val() );
		},

		changeValue: function( evt ) {
			var value = jQuery( evt.target ).val();
			var type = this.model.get( 'type' );
			if ( '' === value ) {
				this.model.set( 'field_type', '' );
				this.model.set( 'field_value', '' );
				this.model.set( 'field_field', '' );
				this.model.set( 'field_subfields', {} );
			} else {
				this.model.set( 'field_value', value );
				// Update subfields
				if( "component" === type ){
					var component = jQuery( evt.target ).closest('.component-table-wrapper').attr('id');
					console.log("changeValue - component: ",component);
					this.updateAjax_Field(component, value, false);
				}
			}
		},

		changeField: function( evt ) {
			var value = jQuery( evt.target ).val();
			if ( '' === value ) {
				this.model.set( 'field_value', '' );
				this.model.set( 'field_field', '' );
				this.model.set( 'field_subfields', {} );
			} else {
				this.model.set( 'field_field', value );
				// Update subfields
				var component = jQuery( evt.target ).closest('.component-table-wrapper').attr('id');
				this.updateAjax_ComponentSubfields(component, value, false);
			}
		},

		changeSubfield: function( evt ) {
			var value = jQuery( evt.target ).val();
			var index = jQuery( evt.target ).attr('data-index');
			var subfield_data = this.model.get( 'field_subfields');
			if(!subfield_data){ subfield_data = {}; }
			subfield_data[index] = value;
			this.model.set( 'field_subfields', subfield_data );
		},

		/**
		 * AJAX Update: "Field" - ACF Field group's field
		 * 
		 * @param {string} component - ID without the "#" of the component parent row
		 * @param {string} field_name - Parent field name/key of the sub fields, should be a repeater
		 * @param {object} saved_fields - OPTIONAL: Pass saved subfields if you want to set pre-existing values
		 */
		updateAjax_Field: function( component, field_name, saved_fields ) {
			saved_fields = typeof saved_fields !== 'undefined' ? saved_fields : "";
			console.log('updateAjax_Field');
			console.log('field_name: ',field_name);
			console.log('saved_fields: ',saved_fields);

			jQuery.post( window.ajaxurl, {
				action: 'gc_component_subfields',
				subfields_data: {
					name: field_name,
				}
			}, function( response ) {
				console.log('RESPONSE: ',response);

				// SUCCESS
				if( response.success ){
					// Ensure response has subfield data
					if( response.data.field_data && response.data.field_data.length ){
						// Build options HTML:
						var options_html = "<option value=''>Unused</option>";
						jQuery.each(response.data.field_data, function(i, field) {
							options_html += "<option value='"+field.key+"'>"+field.label+"</option>";
						});
						// Inject into select fields
						jQuery('#'+component).find('.wp-type-field-select').html(options_html);

						// If existing subfields are passed, update specific dropdown options
						if(saved_fields){
							jQuery('#'+component).find('.wp-type-field-select').val(saved_fields);
						}
					}
				}
				// ERROR
				else{
					console.log('AJAX ERROR!');
				}
			});
		},

		/**
		 * AJAX Update: "Field" - ACF Field group's repeater subfields
		 * 
		 * @param {string} component - ID without the "#" of the component parent row
		 * @param {string} field_name - Parent field name/key of the sub fields, should be a repeater
		 * @param {object} saved_fields - OPTIONAL: Pass saved subfields if you want to set pre-existing values
		 */
		updateAjax_ComponentSubfields: function( component, field_name, saved_fields ) {
			saved_fields = typeof saved_fields !== 'undefined' ? saved_fields : {};
			// console.log('updateSubFields: ',field_name);
			// console.log('saved_fields: ',saved_fields);

			jQuery.post( window.ajaxurl, {
				action: 'gc_component_subfields',
				subfields_data: {
					name: field_name,
				}
			}, function( response ) {
				console.log('RESPONSE: ',response);

				// SUCCESS
				if( response.success ){
					// Ensure response has subfield data
					if( response.data.field_data && response.data.field_data.length ){
						// Build options HTML:
						var options_html = "<option value=''>Unused</option>";
						jQuery.each(response.data.field_data, function(i, field) {
							options_html += "<option value='"+field.key+"'>"+field.label+"</option>";
						});
						// Inject into select fields
						jQuery('#'+component).find('.component-table-inner select').html(options_html);

						// If existing subfields are passed, update specific dropdown options
						if(Object.keys(saved_fields).length){
							var dropdowns = jQuery('#'+component).find('.component-table-inner select').toArray();
							jQuery.each(dropdowns, function(i, dropdown){
								i++;
								jQuery(dropdown).val(saved_fields[i]);
							});
						}
					}
				}
				// ERROR
				else{
					console.log('AJAX ERROR!');
				}
			});
		},

		render : function() {
			var val = this.model.get( 'field_value' );
			var valField = this.model.get( 'field_field' );
			var valSubfields = this.model.get( 'field_subfields' );
			var component;
			console.log("render - val: ",val);
			console.log("render - valField: ",valField);
			console.log("render - valSubfields: ",valSubfields);

			if ( val && ! this.metaKeys.getByValue( val ) ) {
				this.metaKeys.add( { value : val } );
			}
			if ( valField && ! this.metaKeys.getByField( valField ) ) {
				this.metaKeys.add( { field : valField } );
			}
			if ( valSubfields && ! this.metaKeys.getBySubfields( valSubfields ) ) {
				this.metaKeys.add( { subfields : valSubfields } );
			}

			// Init subfields
			if(valField){
				component = this.model.get('name');
				if(valSubfields){
					this.updateAjax_ComponentSubfields(component, valField, valSubfields);
				}
			}

			var json = this.model.toJSON();
			json.metaKeys = this.metaKeys.toJSON();

			this.$el.html( this.template( json ) );

			this.$( '.gc-select2' ).each( function() {
				var $this = jQuery( this );
				var args = {
					width: '250px'
				};

				if ( $this.hasClass( 'gc-select2-add-new' ) ) {
					args.tags = true;
				}

				$this.select2( args );
			} );

			return this;
		}

	});
};
