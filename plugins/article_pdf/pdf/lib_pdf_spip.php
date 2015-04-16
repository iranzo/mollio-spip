<?php

/**
 * class PDF_SPIP extends PDF : 
 */
 
 
class PDF_SPIP extends PDF
{


// haut, gauche,  bas, droite
function SetAllMargins($TopMargin, $LeftMargin, $BottomMargin, $RightMargin)
{
	// gauche, haut, droite
	$this->SetMargins($LeftMargin,$TopMargin,$RightMargin);
	
	// bas
	$this->SetAutoPageBreak(auto, $BottomMargin*3/2);
}


function Header()
{
	global $titre ;
	
	$this->SetY($this->tMargin/2);
	$this->SetLineWidth(0.3);
	$this->Line($this->lMargin - 3, $this->tMargin, $this->w - $this->rMargin + 3, $this->tMargin);
	
	//Police helvetica gras 8
	$this->SetFont('helvetica','B',12);
	$this->SetTextColor(0,0,0);

	$this->Cell(0,$this->tMargin/2, $titre ,0,0,'C');
	
	// $this->tMargin = marge du haut, définie dans FPDF
	$this->Ln(9);
}


/* /// Pied de page du document) 
/* ///////////////////////////// */
function Footer()
{
	global $conf_nom_site , $conf_url_site  ;
	
	$this->SetY(-$this->bMargin/2);	
	$this->SetLineWidth(0.3);
	$this->Line($this->lMargin - 3, $this->GetY(), $this->w - $this->rMargin + 3, $this->GetY());
	
	
	//Police helvetica 8	
	$this->SetFont('helvetica','I',8);
	$this->SetTextColor(0,0,0);

	// Copyright
	$this->Cell(0,6,"Copyright © ".$conf_nom_site ,0,0,'L',0,$conf_url_site );
	
	//Numéro de page
	$this->SetX($this->w-$this->rMargin*2-5);
	$this ->Cell(0,6,'Page '.$this->PageNo().'/{nb}', 0, 1, 'C');
}

function GenerateTitlePage()
{
	global $site, $rubrique, $yahoo, $surtitre, $titre, $soustitre;
	global $logo_site,  $logo_fichier, $logo_lien;
  global $auteur, $descriptif;
	global $copyright;
	global $conf_url_site;
	global $DateParution,$DateMiseEnLigne;
	
	
	// En-tête
	if (file_exists($logo_site))
	{
		$this->Image($logo_site,$this->rMargin+3,$this->tMargin+2,20,20);
	}
	
	$this->SetFont('times','',12);
	$this->SetXY($this->rMargin+25,$this->tMargin+6);
	$this->MultiCell(0,5,"Extrait du " . $site);
	
	$this->SetXY($this->rMargin+25,$this->tMargin+14);
	$this->PutLink($conf_url_site,$conf_url_site);
	
	
	//Surtitre (type du document)
	$this->unhtmlentities($surtitre);
	$this->SetXY(20,92);
	$this->SetFont('courier','B',14);
	$this->MultiCell(170,6,$surtitre,0,'C',0);
	
	
	//Titre centré
	$this->SetXY(20,100);
	$this->SetFont('helvetica','B',32);
	$this->unhtmlentities($titre);
	$this->MultiCell(170,20,$titre,0,'C',0);
	
	
	// Rubriques
	$this->Ln(2);
	$this->SetFont('helvetica','',8);
	$this->MultiCell(0,5,$yahoo,0,'C',0);
	
	// Logo

	if ($logo_fichier!="") {
	$x = $this->GetX();
	$y = $this->GetY();
		$this->SetLink($link);
//		$this->Image($logo_fichier,50,170,'','','','','0');
//		$this->Image($logo_fichier,50-($w/2),170,50,50,'','','0');
		$this->Image($logo_fichier,50-($w/2),170,'','','',$logo_lien,'0');
		$this->SetXY($xi, $yi);
    	}


	//Dates
	$this->SetFont('times','',10);
	
	if ($DateMiseEnLigne) 
	{
		$this->SetXY(110,184);
		$DateMiseEnLigne = $this->unhtmlentities($DateMiseEnLigne);
		$this->MultiCell(0,6,"Date de mise en ligne : $DateMiseEnLigne",0,'L',0);
	}
	
	if ($DateParution) 
	{
		$this->SetXY(110,190);
		$DateParution = $this->unhtmlentities($DateParution);
		$this->MultiCell(0,6,"Date de parution : $DateParution",0,'L',0);
	}
	

	// Descriptif 	
	if ($descriptif)
	{
		
		$this->SetFont('helvetica','B',10) ;
		$this->SetXY($this->rMargin+5,220);
		$this->SetFont('helvetica', 'BU', 10);
		$this->Write(5, 'Description :');
		$this->Ln();
		$this->SetFont('times', '', 8);
		$this->WriteHTML($descriptif,5) ;
	}
	
	if ($copyright)
	{
		$this->SetXY(45,250);
		$this->SetFont('times', 'B', 10);
		$this->MultiCell(120,8,$copyright,'TB','C',0);
	}
}

function GenerateText()
{
 	global $texte, $chapo, $ps, $notes ;
		
	$this->SetFont('helvetica');
	if ($chapo)
	{
		// Chapeau
		$this->SetFont('times','B',13);
		$this->WriteHTML($chapo,5);
		$this->Ln(12);
	}
	
	//Texte - justifie
	$this->SetFont('helvetica','',10);
	$this->WriteHTML($texte,5);
	$this->Ln(12);
	if ($ps) 
	{
		//ps
		$this->SetFont('','I',8);
		$this->WriteHTML("Post-scriptum : ",4);
		$this->WriteHTML($ps,4);
		$this->Ln(8);
	}
	if ($notes) {
		//notes
		$this->SetFont('','',8);
		$this->WriteHTML($notes,3);
		$this->Ln();
	}
}

function BuildDocument()
{
	$this->AddPage();
	$this->GenerateTitlePage();
	$this->AddPage();
	$this->GenerateText();
	
	// On repasse en police à la bonne taille pour le nombre de pages.
	$this->SetFont('helvetica','I',8);
	$this->AliasNbPages();
}
//
}

?>
