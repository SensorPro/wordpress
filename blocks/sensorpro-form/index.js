( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'sensorpro/signup-form', {
		edit: function ( props ) {
			var a = props.attributes;
			function set( key ) {
				return function ( v ) {
					var o = {};
					o[ key ] = v;
					props.setAttributes( o );
				};
			}
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Form settings', 'sensorpro' ), initialOpen: true },
						el( TextControl, { label: __( 'Title', 'sensorpro' ), value: a.title, onChange: set( 'title' ) } ),
						el( TextControl, { label: __( 'Extra tags (comma separated)', 'sensorpro' ), help: __( 'Added on top of the default tags in Settings > SensorPro.', 'sensorpro' ), value: a.tags, onChange: set( 'tags' ) } ),
						el( TextControl, { label: __( 'Button text', 'sensorpro' ), value: a.buttonText, onChange: set( 'buttonText' ) } )
					)
				),
				el( ServerSideRender, { block: 'sensorpro/signup-form', attributes: a } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
