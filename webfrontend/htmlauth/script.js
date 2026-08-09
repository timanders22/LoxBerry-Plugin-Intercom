// Intercom - Oberflaechenskript
//
// Die Adressen stehen NICHT mehr fest im Text. Bei einer Zweitinstallation
// heisst der Ordner intercom_01, und dann zeigten alle Aufrufe auf die
// Vorgaengerinstallation. Der Ordner kommt jetzt aus einem Datenattribut,
// das die aufrufende Seite setzt.
var icAdmin = (document.body && document.body.getAttribute("data-ic-admin")) || "/admin/plugins/intercom";

$( document ).ready(function() {
	$('.msg').text("Loading last picture...");
		// Adresse aus dem HTML holen statt fest eintragen: bei einer
// Zweitinstallation heisst der Ordner intercom_01, und das Token
// gehoert nicht in eine mitgelieferte Datei.
var icBase = (document.body.getAttribute("data-ic-picture") || "");
if (!icBase) { return; }
$.getJSON( icBase, function( data ) {
		if(data.success){
			$('.lastpicture').attr('src',data.image);
		}else{
			$('.msg').text("Error Loading Picture from Intercom");
		}
	});


	$(document).on('click', ".gallery .delbtn",function(event){
		var item = $(this).parents('.container');
		var file = item.find('a').attr('href');
		jQuery.getJSON(icAdmin + '/ajax.php', {f: file}, function(json, textStatus) {
		  item.remove();
		});
		
	});

	$(document).on('click', ".galleryvideo .delbtn",function(event){
		var item = $(this).parents('.container');
		var file = item.find('a').attr('href');
		jQuery.getJSON(icAdmin + '/ajax.php', {f: file,t:'video'}, function(json, textStatus) {
		  item.remove();
		});
		
	});


	$(document).on('click', "#delallvideo",function(event){
		const response = confirm( jQuery('#DELALLCONFIRM').text() );
        if (response) {
        	jQuery.post(icAdmin + '/videoarchive.php?submit=true', function( data ) {
        		location.reload();
			});
        }
	});

	$(document).on('click', "#delallimg",function(event){
		const response = confirm( jQuery('#DELALLCONFIRM').text() );
        if (response) {
        	jQuery.post(icAdmin + '/archive.php?submit=true', function( data ) {
        		location.reload();
			});
        }
	});



});


