<?php

   $GLOBALS['dossier_squelettes'] = 'mollio';

function porcentaje($valor) {
	return $valor /100;
}



?>
<?php

/// Filtre reserve a la production de PDF
/////////////////////////////////////////
function pdf_first_clean($texte) {

 	// $texte = ereg_replace("<p class[^>]*>", "<P>", $texte);

	//Translation des codes iso
	
	// PB avec l'utilisation de <code>
    // $trans = get_html_translation_table(HTML_ENTITIES);
    // $trans = array_flip($trans);
	$trans = get_html_translation_table(HTML_ENTITIES);
	$trans = array_flip($trans);
	$trans["<br />\n"] = "<br>";
	$trans["&#339;"] = "oe";
	$trans["&#8230;"] = "...";
	$trans["&#8217;"] = "'";
	$trans["&#8211;"] = "-";
	$trans["&#8216;"] = "'";
	$trans["&#8220;"] = "\"";
	$trans["&#8221;"] = "\"";
	$trans["&ucirc;"] = "û";
	
	$texte = strtr($texte, $trans);
  		
	// Echappement des "
  	$texte = ereg_replace("\"", "\\\"", $texte);

  	// Traitement des Espaces
 	$texte = ereg_replace("(&nbsp;| )+", " ", $texte);

 	return $texte;
}
/////////////////////////////////////////
//////////////////////////
?>