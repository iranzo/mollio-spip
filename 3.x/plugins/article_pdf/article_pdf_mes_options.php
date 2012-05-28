<?php
function article_pdf_insertion_racourci($arg){
		$icone = find_in_path('img_pack/article_pdf.png');
		$url = generer_url_public('article_pdf',$arg);
		$code = "<a href='$url' title='Enregistrer au format PDF'><img src='$icone' width='24' height='24' alt='Creer un PDF' />Enregistrer au format PDF</a>";
		return $code;
	}
function balise_ARTICLE_PDF_dist($p) {
		if (!is_array($p->param))
			$p->param=array();
		
		// Produire le premier argument {article_pdf}
		$texte = new Texte;
		$texte->type='texte';
		$texte->texte='article_pdf';
		$param = array(0=>NULL, 1=>array(0=>$texte));
		array_unshift($p->param, $param);
		
		// Transformer les filtres en arguments
		for ($i=1; $i<count($p->param); $i++) {
			if ($p->param[$i][0]) {
				if (!strstr($p->param[$i][0], '='))
					break;# on a rencontre un vrai filtre, c'est fini
				$texte = new Texte;
				$texte->type='texte';
				$texte->texte=$p->param[$i][0];
				$param = array(0=>$texte);
				$p->param[$i][1] = $param;
				$p->param[$i][0] = NULL;
			}
		}
		
		// Appeler la balise #MODELE{article_pdf}{arguments}
		if (!function_exists($f = 'balise_modele'))
			$f = 'balise_modele_dist';
		return $f($p);
	}
?>