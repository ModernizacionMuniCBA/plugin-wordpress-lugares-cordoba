
(function($) {

	tinymce.create('tinymce.plugins.lugares_plugin', {
        init: function(ed, url) {
            ed.addCommand('lugares_insertar_shortcode', function() {
                selected = tinyMCE.activeEditor.selection.getContent();
                var content = '';

                ed.windowManager.open({
					title: 'Listado lugares actividad',
					body: [{
						type: 'textbox',
						name: 'cant',
						label: 'Cantidad'
					},{
						type: 'textbox',
						name: 'circuito',
						label: 'Id Circuito'
					},{
						type: 'textbox',
						name: 'categoria',
						label: 'Id Categoria'
					}],
					onsubmit: function(e) {
						ed.insertContent( '[lista_lugares cant="' + e.data.cant + '" circuito="' + e.data.circuito + '" categoria="' + e.data.categoria + '"]' );
					}
				});
                tinymce.execCommand('mceInsertContent', false, content);
            });
            ed.addButton('lugares_button', {title : 'Insertar lugares', cmd : 'lugares_insertar_shortcode', image: url.replace('/js', '') + '/images/logo-shortcode.png' });
        },   
    });
    tinymce.PluginManager.add('lugares_button', tinymce.plugins.lugares_plugin);
})(jQuery);