jQuery(function($){
	/*----------  Fill in new type post entries  ----------*/
	$('#acf-field_5889dd80bc33c').on('blur', function(){
		var singularName = $("#acf-field_5889dd80bc33c").val(); //Get singular name
		var pluralName = singularName; // Set initial plural name

		// Is Language pt-BR
		if( $('html').attr('lang') == 'pt-BR' ) {
			if( singularName[singularName.length -1] != 's' ){
				if( singularName[singularName.length -1] == 'm' ){
					pluralName = singularName.slice(0, -1)+'ns';
				} else if ( singularName.substr(-2) == 'ão' ) {
					pluralName = singularName.slice(0, -2)+'ões';
				} else if ( (singularName[singularName.length -1] == 'r' || singularName[singularName.length -1] == 'z') && singularName.toLowerCase() != 'banner' ) {
					pluralName = singularName+'es';
				} else if ( singularName.toLowerCase() == 'banner' ) {
					pluralName = 'Banners';
				} else {
					pluralName = singularName+'s';
				}
			}
			// Set Plural Name
			$("#acf-field_5889de236a588").val(pluralName);
			// Set Labels
			if( $('#acf-field_5889df8ba111b').val() == '' ) { $('#acf-field_5889df8ba111b').val(pluralName); }else { $('#acf-field_5889df8ba111b').val(pluralName); } // Menu name
			if( $('#acf-field_5889e03aa111c').val() == '' ) { $('#acf-field_5889e03aa111c').val(pluralName); }else { $('#acf-field_5889e03aa111c').val(pluralName); } // Menu name
			if( $('#acf-field_5889e0d1a1120').val() == '' ) { $('#acf-field_5889e0d1a1120').val('Todos os ' + pluralName); }else { $('#acf-field_5889e0d1a1120').val('Todos os ' + pluralName); } // All Items
			if( $('#acf-field_5889e106a1121').val() == '' ) { $('#acf-field_5889e106a1121').val('Adicionar Novo ' + singularName); }else { $('#acf-field_5889e106a1121').val('Adicionar Novo ' + singularName); } // Add New Item
			if( $('#acf-field_5889e119a1122').val() == '' ) { $('#acf-field_5889e119a1122').val('Adicionar Novo'); }else { $('#acf-field_5889e119a1122').val('Adicionar Novo'); } // Add New
			if( $('#acf-field_5889e26ebd359').val() == '' ) { $('#acf-field_5889e26ebd359').val('Novo ' + singularName); }else { $('#acf-field_5889e26ebd359').val('Novo ' + singularName); } // New Item
			if( $('#acf-field_5889e532bd35a').val() == '' ) { $('#acf-field_5889e532bd35a').val('Editar ' + singularName); }else { $('#acf-field_5889e532bd35a').val('Editar ' + singularName); } // Edit Item
			if( $('#acf-field_5889e555bd35b').val() == '' ) { $('#acf-field_5889e555bd35b').val('Atualizar ' + singularName); }else { $('#acf-field_5889e555bd35b').val('Atualizar ' + singularName); } // Update Item
			if( $('#acf-field_5889e585bd35c').val() == '' ) { $('#acf-field_5889e585bd35c').val('Ver ' + singularName); }else { $('#acf-field_5889e585bd35c').val('Ver ' + singularName); } // See Item
			if( $('#acf-field_5889e5a3bd35d').val() == '' ) { $('#acf-field_5889e5a3bd35d').val('Ver ' + pluralName); }else { $('#acf-field_5889e5a3bd35d').val('Ver ' + pluralName); } // See Items
			if( $('#acf-field_5889e5c7bd35e').val() == '' ) { $('#acf-field_5889e5c7bd35e').val('Pesquisar ' + pluralName); }else { $('#acf-field_5889e5c7bd35e').val('Pesquisar ' + pluralName); } // Search Item
			if( $('#acf-field_5889e5e4bd35f').val() == '' ) { $('#acf-field_5889e5e4bd35f').val('Nada Encontrado'); }else { $('#acf-field_5889e5e4bd35f').val('Nada Encontrado'); } // Not Found
			if( $('#acf-field_5889e5fbbd360').val() == '' ) { $('#acf-field_5889e5fbbd360').val('Nada Encontrado na Lixeira'); }else { $('#acf-field_5889e5fbbd360').val('Nada Encontrado na Lixeira'); } // Not Found in Trash

		// Is Language en-US
		}else{
			if( singularName.substr(-2) == 'ss' || singularName.substr(-2) == 'ch' || singularName.substr(-2) == 'sh' ){
				pluralName = singularName+'es';
			}else if( singularName[singularName.length -1] == 's' || singularName[singularName.length -1] == 'x' || singularName[singularName.length -1] == 'z' || singularName[singularName.length -1] == 'o' ){
				pluralName = singularName+'es';
			}else if( singularName[singularName.length -1] == 'y') {
				pluralName = singularName.slice(0, -1)+'ies';
			}else if( singularName.substr(-2) == 'fe') {
				pluralName = singularName.slice(0, -2)+'ves';
			}else if( singularName[singularName.length -1] == 'f') {
				pluralName = singularName.slice(0, -2)+'ves';
			}
			// Set Plural Name
			$("#acf-field_5889de236a588").val(pluralName);
			// Set Labels
			if( $('#acf-field_5889df8ba111b').val() == '' ) { $('#acf-field_5889df8ba111b').val(pluralName); }else { $('#acf-field_5889df8ba111b').val(pluralName); } // Menu name
			if( $('#acf-field_5889e0d1a1120').val() == '' ) { $('#acf-field_5889e0d1a1120').val('All ' + pluralName); }else { $('#acf-field_5889e0d1a1120').val('All ' + pluralName); } // All Items
			if( $('#acf-field_5889e106a1121').val() == '' ) { $('#acf-field_5889e106a1121').val('Add New ' + singularName); }else { $('#acf-field_5889e106a1121').val('Add New ' + singularName); } // Add New Item
			if( $('#acf-field_5889e119a1122').val() == '' ) { $('#acf-field_5889e119a1122').val('Add New'); }else { $('#acf-field_5889e119a1122').val('Add New'); } // Add New
			if( $('#acf-field_5889e26ebd359').val() == '' ) { $('#acf-field_5889e26ebd359').val('New ' + singularName); }else { $('#acf-field_5889e26ebd359').val('New ' + singularName); } // New Item
			if( $('#acf-field_5889e532bd35a').val() == '' ) { $('#acf-field_5889e532bd35a').val('Edit ' + singularName); }else { $('#acf-field_5889e532bd35a').val('Edit ' + singularName); } // Edit Item
			if( $('#acf-field_5889e555bd35b').val() == '' ) { $('#acf-field_5889e555bd35b').val('Update ' + singularName); }else { $('#acf-field_5889e555bd35b').val('Update ' + singularName); } // Update Item
			if( $('#acf-field_5889e585bd35c').val() == '' ) { $('#acf-field_5889e585bd35c').val('See ' + singularName); }else { $('#acf-field_5889e585bd35c').val('See ' + singularName); } // See Item
			if( $('#acf-field_5889e5a3bd35d').val() == '' ) { $('#acf-field_5889e5a3bd35d').val('See ' + pluralName); }else { $('#acf-field_5889e5a3bd35d').val('See ' + pluralName); } // See Items
			if( $('#acf-field_5889e5c7bd35e').val() == '' ) { $('#acf-field_5889e5c7bd35e').val('Search ' + pluralName); }else { $('#acf-field_5889e5c7bd35e').val('Search ' + pluralName); } // Search Item
			if( $('#acf-field_5889e5e4bd35f').val() == '' ) { $('#acf-field_5889e5e4bd35f').val('Not Found'); }else { $('#acf-field_5889e5e4bd35f').val('Not Found'); } // Not Found
			if( $('#acf-field_5889e5fbbd360').val() == '' ) { $('#acf-field_5889e5fbbd360').val('Not Found in Trash'); }else { $('#acf-field_5889e5fbbd360').val('Not Found in Trash'); } // Not Found in Trash
		}

	});

});
