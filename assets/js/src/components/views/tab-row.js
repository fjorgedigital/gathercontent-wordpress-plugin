module.exports = function( app, _meta_keys ) {
	return app.views.base.extend({
		tagName : 'tr',
		template : wp.template( 'gc-mapping-tab-row' ),

		events : {
			'change .wp-type-select'       : 'changeType',
			'change .wp-type-value-select' : 'changeValue',
			'change .wp-type-field-select' : 'changeValueField',
			'click  .gc-reveal-items'      : 'toggleExpanded'
		},

		initialize: function() {
			this.listenTo( this.model, 'change:field_type', this.render );
			// console.log("this.model: ",this.model);

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
			if ( '' === value ) {
				this.model.set( 'field_value', '' );
				this.model.set( 'field_type', '' );
			} else {
				this.model.set( 'field_value', value );
			}
		},

		changeValueField: function( evt ) {
			var value = jQuery( evt.target ).val();
			if ( '' === value ) {
				this.model.set( 'field_value', '' );
				this.model.set( 'field_field', '' );
			} else {
				this.model.set( 'field_field', value );
				// Update subfields
				var component = jQuery( evt.target ).closest('.component-table-wrapper').attr('id');
				this.updateSubFields(component, value, false);
			}
		},

		/**
		 * Update Sub Fields
		 * 
		 * @param {string} component - ID without the "#" of the component parent row
		 * @param {string} field_name - Parent field name/key of the sub fields, should be a repeater
		 * @param {object} saved_subfields - OPTIONAL: Pass saved subfields if you want to set pre-existing values
		 */
		updateSubFields: function( component, field_name, saved_subfields ) {
			saved_subfields = typeof saved_subfields !== 'undefined' ? saved_subfields : {};
			// console.log('updateSubFields: ',field_name);
			// console.log('saved_subfields: ',saved_subfields);

			// From sync.js
			jQuery.post( window.ajaxurl, {
				action: 'gc_component_subfields',
				subfields_data: {
					field_name: field_name,
				}
			}, function( response ) {
				console.log('RESPONSE: ',response);

				// SUCCESS
				if( response.success ){
					// Ensure response has subfield data
					if( response.data.sub_fields && response.data.sub_fields.length ){
						// Build options HTML:
						var options_html = "<option value=''>Unused</option>";
						jQuery.each(response.data.sub_fields, function(i, field) {
							options_html += "<option value='"+field.key+"'>"+field.label+"</option>";
						});
						// Inject into select fields
						jQuery('#'+component).find('.component-table-inner select').html(options_html);

						// If existing subfields are passed, update specific dropdown options
						if(Object.keys(saved_subfields).length){
							var dropdowns = jQuery('#'+component).find('.component-table-inner select').toArray();
							jQuery.each(dropdowns, function(i, dropdown){
								i++;
								jQuery(dropdown).val(saved_subfields[i]);
							});
							// Object.keys(saved_subfields).forEach(function(key) {
							// 	// console.log(key + ": " + person[key]);
							// 	jQuery(fields[key--]).val(saved_subfields[key]);
							// });
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
			if(valField && valSubfields){
				var component = this.model.get('name');
				this.updateSubFields(component, valField, valSubfields);
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
