<?php
	$p = explode(basename(_DIR_PLUGINS)."/",str_replace('\\','/',realpath(dirname(__FILE__))));
	define('_DIR_PLUGIN_ARTICLE_PDF',(_DIR_PLUGINS.end($p)));

	function pdf_first_clean($texte)
	{
			// $texte = ereg_replace("<p class[^>]*>", "<P>", $texte);
			//Translation des codes iso
			// PB avec l'utilisation de <code>
			$trans = get_html_translation_table(HTML_ENTITIES);
			$trans = array_flip($trans);
			$trans["<br />\n"] = "<BR>";        // Pour éviter que le \n ne se tranforme en espace dans les <DIV class=spip_code> (TT, tag SPIP : code)
			$trans['&#176;'] = "°";
			$trans["&#339;"] = "oe";
			$trans["&#8211;"] = "-";
			$trans["&#8216;"] = "'";
			$trans["&#8217;"] = "'";		
			$trans["&#8220;"] = "\"";
			$trans["&#8221;"] = "\"";
			$trans["&#8230;"] = "...";
			$trans["&#8364;"] = "Euros";
			$trans["&ucirc;"] = "û";
			$trans['->'] = '-»';
			$trans['<-'] = '«-';
			$trans['&nbsp;'] = ' ';
			$texte = strtr($texte, $trans);
			
			$texte = unicode2charset(charset2unicode($texte), 'iso-8859-1'); // repasser tout dans un charset acceptable par export PDF
	
			return $texte;
	}


?>