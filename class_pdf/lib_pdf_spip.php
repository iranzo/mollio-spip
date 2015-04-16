<?

/**
 * class PDF_SPIP extends PDF : 
 */
 
 
class PDF_SPIP extends PDF
{

function Header()
{
	global $titre ;
	
	//Logo
	//	$this->Image('logo.gif',10,8,33);
	$this->SetY(5);
	$this->SetLineWidth(0.3);
	$this->Line($this->lMargin - 5, 13, $this->w - $this->rMargin + 5, 13);
	$this->unhtmlentities($titre);
	$this -> SetFont('helvetica','',10);
	$this->SetTextColor(128);
	$this->MultiCell(0,4,$titre."\n\n");
	
	
}


/* /// Pied de page du document) 
/* ///////////////////////////// */
function Footer()
{
	global $conf_nom_site , $conf_url_site  ;
	
	//Positionnement à 2.0 cm du bas
	$this->SetY(-20);	
	$this->SetLineWidth(0.3);
	$this->Line($this->lMargin - 5, $this->GetY(), $this->w - $this->rMargin + 5, $this->GetY());
	
	//Police helvetica 8	
	$this->SetFont('helvetica','I',8);
	$this->SetTextColor(0,0,0);

	$this->Cell(0,6,$conf_nom_site ,0,	0,'L',0,$conf_url_site );
	
	//Numéro de page
	$this -> Cell(0,6,$GLOBALS["pagina"] . ' '.$this->PageNo().'/{nb}', 0, 1, 'C');
}

function TitreChapitre()
{
	global $site, $rubrique, $yahoo, $surtitre, $titre, $soustitre, $logo_fichier, $logo_lien, $date, $auteur, $descriptif;
	
	$z = 55;
	
	$this->Ln(45);
	$this->SetFont('courier','',26);
	$this->SetTextColor(128);
	//Titre centré
	$this->unhtmlentities($site);
	$this->MultiCell(0,9,$site,0,'C',0);
	$this->SetFont('helvetica','',8);
	$this->MultiCell(0,5,$yahoo,0,'C',0);
	//Saut de ligne
	$this->Ln(60);
	
	$this->SetLineWidth(0.3);
	
	//Rubriques
	$this->unhtmlentities($rubrique);
	$this->SetFont('courier','',20);
	$this->MultiCell($z+20,9,$rubrique,0,'L',0);
	$this->Ln();

	// Logo
	$x = $this->GetX();
	$y = $this->GetY();

	if ($logo_fichier!="") {
		$this->SetLink($link);
		$this->Image('IMG/'.$logo_fichier,$x,$y,'',20,'',$logo_lien);
		}

	$this->SetXY($x+$z+25, $y-16);
	
	if ($surtitre) {
		//Surtitre
		$this->unhtmlentities($surtitre);
		$this->SetX($x+$z+25);
		$this->SetFont('times','',16);
		$this->MultiCell(0,6,$surtitre."\n\n",L,'L',0);
		}
	
	$this->SetTextColor(0);
	
	//Titre
	$this->unhtmlentities($titre);
	$this->SetX($x+$z+25);
	$this->SetFont('times','B',22);
	$this->MultiCell(0,8,$titre."\n\n",L,'L',0);
	if ($soustitre) {
		//Soustitre
		$this->unhtmlentities($soustitre);
		$this->SetX($x+$z+25);
		$this->SetFont('times','',18);
		$this->MultiCell(0,6,$soustitre."\n\n",L,'L',0);
		}
	if ($auteur) {
		//Auteur
		$this->unhtmlentities($auteur);
		$this->SetX($x+$z+25);
		$this->SetFont('times','',16);
		$this->MultiCell(0,6,$auteur."\n",L,'R',0);
		}
	if ($date) {
		//Date
		$this->unhtmlentities($date);
		$this->SetX($x+$z+25);
		$this->SetFont('times','',12);
		$this->MultiCell(0,6,$date."\n",L,'R',0);
		}
	if ($descriptif) {
		// Descriptif 		$this->SetFont('helvetica','B',10) ;
		$this->SetXY($this->lMargin, $y + $z - 10);
		$this->SetFont('helvetica', 'BU', 10);
		$this->Write(5, $GLOBALS["resumen"].' :');
		$this->Ln();
		$this->SetFont('times', '', 8);
		$this->WriteHTML($descriptif) ;
		$this->Ln(12);
	}
	$this->Ln(25);
}

function CorpsChapitre()
{
 	global $texte, $chapo, $ps, $notes ;
		
	$this->SetFont('times');
	if ($chapo) {
		// Chapeau
		$this->SetFont('helvetica','B',10);
		$this->WriteHTML($chapo);
		$this->Ln(12);
	}
	
	//Texte - justifie
	$this->SetFont('helvetica','',10);
	$this->WriteHTML($texte);
	$this->Ln(12);
	if ($ps) {
		//ps
		$this->SetFont('','I',8);
		$this->WriteHTML("Post-scriptum : ");
		$this->WriteHTML($ps);
		$this->Ln(8);
	}
	if ($notes) {
		//notes
		$this->SetFont('','',8);
		$this->WriteHTML($notes);
		$this->Ln();
	}
}

function AjouterChapitre()
{
	$this->AddPage();
	$this->TitreChapitre();
	$this->AddPage();
	$this->CorpsChapitre();
	
	// On repasse en police à la bonne taille pour le nombre de pages.
	$this->SetFont('helvetica','I',8);
	$this->AliasNbPages();
}
//
}

?>