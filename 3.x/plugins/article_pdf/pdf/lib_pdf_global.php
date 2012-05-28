<?php
/**
 * class PDF extends FPDF : FPDF/tutoriel/tuto6.htm
 * 
 * Février-Août 2003 : Jérôme Fenal (jerome.fenal@logicacmg.com)
 * Ajout de la prise en compte des tableaux, tag <code>, et diverses autres choses de SPIP
 */ 

 //  Fichier de dump pour debug
define (DUMP_FILE_FULL_PATH_NAME,"Dump.txt");

 
class PDF extends FPDF
{
var $HREF;
var $texteAddSpace;
var $SRC;
var $columnProp=array();		# propriétés de la ligne
var $lineProp=array();		# propriétés de la ligne
var $inFirstRow;		# flag si première ligne en cours
var $TableX;			# abscisse du tableau
var $HeaderColor;
var $RowColors;
var $tableProp=array();

var $ProcessingBloc=0;
var $BlocContent=array();
var $BlocTags=array();

var $ProcessingTable=false;	# =1 : en cours lecture table
var $ProcessingCadre=false;	# =1 : en cours lecture contenu d'un cadre SPIP (TEXTAREA HTML)
var $tableCurrentCol;	# numéro de cellule courante
var $tableCurrentRow;	# Numero de ligne courante pendant la lecture d'un tableau
var $tableContent=array();		# Contenu de la table courante pendant son absorption. Non réentrant car SPIP ne permet pas de faire
						# de table dans une autre table.
var $listDepth=0;		# profondeur courante de liste à puce
var $listParm = array();	# paramètres des listes à puces en fonction du niveau

var $TopLinkIDArray = array(); #Sauve les IDs des liens internes (notes dans le texte)
var $TopLinkIDArrayIt = 0; #Itérateur dans le tableau des IDs des liens internes

var $BottomLinkIDArray = array(); #Sauve les IDs des liens internes (notes en fin de document)
var $BottomLinkIDArrayIt = 0; #Itérateur dans le tableau des IDs des liens internes

var $FirstIteration = TRUE;  # booleen pour la génération des liens
var $CurrentTag=array();


function Build($OutputFileFullPathName)
{
	$this->Open();
	
	$this->FirstIteration=TRUE;
	$this->BuildDocument() ;
	
	$this->ResetBuffer();
	$this->tag=0;
	$this->SetFont('','');
	
	$this->FirstIteration=FALSE;
	$this->BuildDocument() ;

	$this->Output($OutputFileFullPathName);
	
	$this->Close();
}


function AddCol($field=-1,$width=-1,$align='L')
{
	//Ajoute une colonne au tableau
	if($field==-1)
	{
		$field=count($this->columnProp);
	}
	
	$this->columnProp[$field]=array('f'=>$field,'w'=>$width,'a'=>$align);
	#$this->Write(5, "Ajout de colonne : ".$field."/".$width."/".$align); $this->Ln();
}


function PDF($orientation='P', $unit='mm', $format='A4')
{
	//Appel au constructeur parent
	$this->FPDF($orientation, $unit, $format);
	$this->SetCompression(1);
	
	//$this->InitDumpFile();
	
	$this->HREF='';
}

function unhtmlentities($string)
{
	$trans_tbl = get_html_translation_table (HTML_ENTITIES);
	$trans_tbl = array_flip ($trans_tbl);
	$ret = strtr ($string, $trans_tbl);
	return preg_replace('/&#(\d+);/me', "chr('\\1')",$ret);
}

function WriteHTML($html,$LineFeedHeight)
{
	$this->texteAddSpace=false;
	//Parseur HTML, enlevé pour une meilleure récupération des tag.
  //Il faut détecter les vraies balises "<" HTML et pas les < de texte "&lt;" HTML 
  //Parseur remis + loin pour l'édition du texte
	//$html=$this->unhtmlentities($html);
	
	$a=preg_split(',(<[/a-zA-Z].*>),Ums', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

	// $a = le tableau de tags
	// $i = index de l'élément courant
	// $e = valeur de l'élément courant
	foreach($a as $i=>$e) 
  {
		//Balise 
	$Balise= preg_match(',<(?=[/a-zA-Z])(/)?([/a-zA-Z]+)((\s.*|/)?)>,',$e,$match);
	if ($Balise){
      $tag=strtoupper($match[2]);
			$closing = $match[1]=="/";
			
			if (($this->ProcessingBloc) AND (!in_array($tag,$this->BlocTags[$this->ProcessingBloc-1])))
				$this->BlocContent[$this->ProcessingBloc-1] .= $e;
			else {
				if ($closing)
				// C'est une balise fermante
					$this->CloseTag($tag,$LineFeedHeight);
				else
					$this->OpenTag($tag,$e,$LineFeedHeight);
			}
		}
		// Contenu
		else {
			if (!in_array('TEXTAREA',$this->CurrentTag))
				$e=str_replace("\n",' ',$e);
			if (strlen($e)){
				$te = trim($e);
				if (!$this->texteAddSpace)
					$this->texteAddSpace = (strlen($te)==0) | ($e{0}!=$te{0});
				$next_add_space = (strlen($te)==0) | (substr($e,-1)!=substr($te,-1));
				$e = $te;
			}
			if(strlen($e)){
				$e = $this->texteAddSpace?" $e":$e;
				$this->texteAddSpace = $next_add_space;
				# Attention, ce mécanisme ne permet pas de traiter les liens dans les tableaux...
				# ni les tableaux dans les tableaux, d'ailleurs...
				if (($this->ProcessingBloc))
					$this->BlocContent[$this->ProcessingBloc-1] .= $e;
				else 
				{
					// C'est un lien. Il faut faire la distinction entre lien externe, lien interne et note de bas de page (couples ancre + lien interne)
					if($this->HREF) 
					{
						$Link=$this->HREF;
						$Text=$e;
						if ( strstr($Link,"http:") || strstr($Link,"mailto:") || strstr($Link,"ftp:") )
						{
							// C'est un lien  externe
							$this->PutLink($Link, $Text);
						}
						else
						{
							// C'est une note (référence dans le texte)
							if ( strstr($Link,"#nb") )
							{
								if ($this->FirstIteration)
								{
									$LinkID=$this->AddLink();
									$this->SetLink($LinkID,-1,-1);
									$this->TopLinkIDArray[]=$LinkID;
									$this->PutLink($Link,$Text);  // Lien bidon (première itération)
								}
								else
								{
									$LinkID=$this->BottomLinkIDArray[$this->BottomLinkIDArrayIt++];
									$this->PutLink($LinkID,$Text);   // Bon lien  (deuxième itération)
								}
							}
							// C'est une note (détail de bas de texte)
							else if ( strstr($Link,"#nh") )
							{
							
								// C'est le lien "#nh1" (le premier) : on met un trait séparateur
								if ( strlen($Link)==4 && $Link[3]=="1" )
								{
									$this->SetLineWidth(0.3);
									$this->Line($this->lMargin, $this->GetY()-5, $this->w - $this->rMargin, $this->GetY()-5);
								}
								
								if ($this->FirstIteration)
								{
									$LinkID=$this->AddLink();
									$this->SetLink($LinkID,-1,-1);
									$this->BottomLinkIDArray[]=$LinkID;
									$this->PutLink($Link,$Text);  // Lien bidon (première itération)
								}
								else
								{
									$LinkID=$this->TopLinkIDArray[$this->TopLinkIDArrayIt++];
									$this->PutLink($LinkID,$Text);		// Bon lien  (deuxième itération)
								}
								
							}
							// C'est un lien interne
							else
							{
								$WebSiteURL=entites_html(lire_meta("adresse_site"));
								// Bug d'interprétation du point d'interrogation remplacé par 3 points. Correctif ici
								$Link=str_replace("...","?",$Link);
								
								$this->PutLink($WebSiteURL . "/" . $Link, $Text);
							}
						}
					} else 
					{
					//Parseur remis ici
						$e=$this->unhtmlentities($e);
            $this->Write(5,$e);
					}
				}
			}
		}
	}
  
}

function OpenTag($tag,$e,$LineFeedHeight)
{
	$needclosing = true;	
	//Balise ouvrante
	if ($tag=='B' || $tag=='U' || $tag=='I')
	{
		$this->SetStyle($tag,true);
	}
	
	if ($tag=='STRONG')
	{
		$this->SetStyle('B',true);
	}
	
	if ($tag=='EM')
	{
		$this->SetStyle('I',true);
	}

	if($tag=='A')
	{
		$this->HREF=extraire_attribut($e,'href');
		$this->texteHREF="";
		if ($this->texteAddSpace) {
			$this->Write(5," ");
			$this->texteAddSpace = false;
		}
	}
	if($tag=='BR') {
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln($LineFeedHeight);
		$needclosing = false;
		$this->texteAddSpace = false;
	}

	if($tag=='P') {
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln(1.5*$LineFeedHeight);
		$this->texteAddSpace = false;
	}
	if($tag=='DIV') {
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln(1*$LineFeedHeight);
		$this->texteAddSpace = false;
	}

	if($tag=='CODE') {
//		$this->Write(5,"<code>\n");
		$this->SetFont('courier','', 8);
		$this->SetTextColor(0, 0, 255);
		$this->ProcessingCadre=true;
		$this->ProcessingBloc++;
		$this->BlocTags[$this->ProcessingBloc-1]=array("CODE");
		$this->BlocContent[$this->ProcessingBloc-1]="";
	}
	
	if($tag=='H3')
	{
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln($LineFeedHeight*3);
		$this->SetStyle($tag='B',true,14);
		$this->texteAddSpace = false;
	}

	if($tag=='UL' or $tag=='OL') {
		$this->SetLeftMargin($this->lMargin+7);
		$this->listDepth++;
		$this->listParm[$this->listDepth]['type']=$tag;
		$this->listParm[$this->listDepth]['curr']=0;		# numéro si OL
	}

	if($tag=='LI'){ 
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln();
		$this->listParm[$this->listDepth]['curr']++;
		$this->SetX($this->GetX()-7);
		if ($this->listParm[$this->listDepth]['type']=='OL')
			$this->Cell(7,5,$this->listParm[$this->listDepth]['curr'].'.',0,0,'C'); 
		else
			$this->Cell(7,5,chr(149),0,0,'C'); 
	}

	if ($tag=='IMG') {
    $this->SRC=extraire_attribut($e,'src');

		// si l'image est manquante mettre un lien avec le texte alt
		if (!@is_readable($this->SRC)){
			$alt = extraire_attribut($e,'alt');
			if ($alt==NULL) $alt = $this->SRC;
			//var_dump("img:href=".$this->HREF.':');
			if ($this->HREF=="")
				$this->Write(5,"[$alt]");
			else 
				$this->PutLink($this->HREF,"[$alt]");
		}
		else
		{
			$size=getimagesize($this->SRC);		# Attention, utilisation de GD !!! FPDF ne sait pas lire les images à moitié... et je n'ai pas envie de surcharger la méthode Image...
			if ($size[0] < 30 && $size[1] < 30) {
				# pixel / 3 pour avoir des cm. Petite cuisine...
				$imgX=$size[0]/3;
				$imgY=$size[1]/3;
				$yoffset=$imgY/4;
				if ($this->GetY() + $imgY > $this->h - $this->bMargin)
					$this->AddPage();
				$this->Image($this->SRC, $this->GetX(), $this->GetY()-$yoffset, $imgX, $imgY,'',$this->HREF);
				$this->SetX($this->GetX()+$size[0]/2);
			} else if ($size[0] < 600 && $size[1] < 600) {
				$pwidth=$this->w-$this->lMargin-$this->rMargin;
				$ratio = 0.24;	# ce qui fait environ 600 pixels sur 16cm d'espace utile (160/600) - 2 pouillièmes
				$imgX=$size[0]*$ratio;
				$imgY=$size[1]*$ratio;
				if ($this->GetY() + $imgY > $this->h - $this->bMargin) {
					if ($this->GetY() + $imgY*0.8 > $this->h - $this->bMargin) {
						$this->AddPage();
					} else {
						$imgX=$imgX*0.8;
						$imgY=$imgY*0.8;
					}
				}
				$this->Image($this->SRC, $this->GetX()+($pwidth-$imgX)/2, $this->GetY(), $imgX, $imgY,'',$this->HREF);
				$this->SetY($this->GetY()+$imgY);
			} else {
				// les deux dimensions sont supérieurs à 600 pixels
				$pwidth=$this->w-$this->lMargin-$this->rMargin;
				$ratioX = $pwidth / $size[0];
				$plen=$this->h-$this->GetY()-$this->bMargin-20;		// on retire 20mm pour placer le cartouche de l'image
				$ratioY = $plen / $size[1];
				$ratio = 0.24;	# ce qui fait environ 600 pixels sur 16cm d'espace utile (160/600) - 2 pouillièmes
				$imgX=$size[0]*$ratio;
				$imgY=$size[1]*$ratio;
	
				if ($size[1] > 900 || ($plen - ($size[1]*$ratio)  < 0)) {
					if ($plen - ($size[1]*$ratio*0.8)  < 0) {
						$this->AddPage();
						$plen=$this->h-$this->GetY()-$this->bMargin-20;	// toujours la marge du cartouche
						$ratioY = $plen / $size[1];
					} else {
						$ratioX *= 0.8;
						$ratioY *= 0.8;
					}
				}
	
				$ratio=min(0.24, $ratioX, $ratioY);
	
				$imgX=$size[0]*$ratio;
				$imgY=$size[1]*$ratio;
	
				$this->Image($this->SRC, $this->GetX()+($pwidth-$imgX)/2, $this->GetY(), $imgX, $imgY,'',$this->HREF);
				$this->SetY($this->GetY()+$imgY);
			}
		}
	}

	if($tag=='TT' or $tag=='TEXTAREA') {
		$this->SetFont('courier','', 8);
		$this->SetTextColor(255, 0, 0);
		if ($tag=='TEXTAREA'){
			$this->ProcessingCadre=true;
			}
	}

	if($tag=='TABLE') {
		$this->ProcessingTable=true;
		$this->inFirstRow=1;
		# on commence une table
		if(!isset($this->tableProp['width']))
			$this->tableProp['width']=0;

		if($this->tableProp['width']==0)
			$this->tableProp['width']=$this->w-$this->lMargin-$this->rMargin;
		if(!isset($this->tableProp['align']))
			$this->tableProp['align']='C';
		if(!isset($this->tableProp['padding']))
			$this->tableProp['padding']=$this->cMargin;
		$cMargin=$this->cMargin;
		$this->cMargin=$this->tableProp['padding'];
		if(!isset($this->tableProp['HeaderColor']))
			$this->tableProp['HeaderColor']=array(200, 200, 200);
		$this->HeaderColor=$this->tableProp['HeaderColor'];
		if(!isset($this->tableProp['color1']))
			$this->tableProp['color1']=array();
		if(!isset($this->tableProp['color2']))
			$this->tableProp['color2']=array(230, 230, 230);
		$this->RowColors=array($this->tableProp['color1'], $this->tableProp['color2']);
		$this->tableCurrentRow=0;
	}

	if($tag=='TR') {
		# on commence une ligne
		$this->tableCurrentCol=0;
		$this->tableCurrentRow++;
		if (extraire_attribut($e,'class') == 'row_first') {
			$this->ProcessingTH=true;
		}
	}

	if($tag=='TH' or $tag=='TD') {
		# Cellule (pas titre)
		$this->tableCurrentCol += 1;
		$colspan = extraire_attribut($e,'colspan');
		if ($this->inFirstRow) {
			if ($colspan)
				for($i=0;$i<$colspan;$i++){
					$this->nCols=$this->tableCurrentCol;
					$this->AddCol();
				}
			else {
				$this->nCols=$this->tableCurrentCol;
				$this->AddCol();
			}
		}
		$this->ProcessingBloc++;
		$this->BlocTags[$this->ProcessingBloc-1]=array("TH","TD");
		$this->BlocContent[$this->ProcessingBloc-1]="";
	}
	if($tag=='BLOCKQUOTE') {
		$this->ProcessingBloc++;
		$this->BlocTags[$this->ProcessingBloc-1]=array("BLOCKQUOTE");
		$this->BlocContent[$this->ProcessingBloc-1]='';
	}
	if($tag=='TEXTAREA') {
		$this->ProcessingBloc++;
		$this->BlocTags[$this->ProcessingBloc-1]=array("TEXTAREA");
		$this->BlocContent[$this->ProcessingBloc-1]="";
	}
	if($tag=='HR') 
	{
		# Ligne horizontale
		$this->SetLineWidth(0.3);
		$this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
		$needclosing = false;
		$this->texteAddSpace = false;
	}
	if ((substr($e,-2)!="/>") && $needclosing)
		$this->CurrentTag[]=$tag;	
}

function CloseTag($tag,$LineFeedHeight)
{
	if($tag=='B' || $tag=='U' || $tag=='I')
		$this->SetStyle($tag,false);
	
	if($tag=='STRONG')
		$this->SetStyle('B',false);
	
	if($tag=='EM')
		$this->SetStyle('I',false);
		
	if($tag=='A'){
		$this->HREF='';
	}
		
	if($tag=='P'){
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln($LineFeedHeight);
	}

	if($tag=='CODE') {
    $this->ProcessingCadre=false;
		if (strlen($content=$this->BlocContent[$this->ProcessingBloc-1])){
			$this->ProcessingBloc--;
			$this->BlocShow(0,$content,1,$LineFeedHeight);
      }
		$this->SetTextColor(0);
		$this->SetFont('helvetica','',10);
//    $this->Write(5,"\n<\code>");
	}

	if($tag=='H3'){		
		$this->SetStyle($tag='B',false,10);
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln($LineFeedHeight);
	}
	
	if($tag=='UL' or $tag=='OL') { 
		$this->SetLeftMargin($this->lMargin-7); 
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln();
		$this->listParm[$this->listDepth]=array();
		$this->listDepth--;
	} 
	if($tag=='TT') { 
		$this->SetFont('helvetica','',10);
		$this->SetTextColor(0);
	}
	if($tag=='TD' or $tag=='TH') {
		if (!strlen($this->BlocContent[$this->ProcessingBloc-1]))
			$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['content']=" ";
		else 
			$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['content']=$this->BlocContent[$this->ProcessingBloc-1];
		if ($tag=='TH')
			$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['TH']=1;
		else
			$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['TH']=0;
		$this->ProcessingBloc--;
	}
	if($tag=='TR') {
		$this->inFirstRow=0;	# on a fini une ligne donc la première aussi
	}
	if($tag=='TABLE') {
		$this->TableShow('C',$LineFeedHeight);
		$this->inFirstRow=0;
		$this->ProcessingTable=false;
		$this->cMargin=$cMargin;
		$this->columnProp=array();
		$this->tableContent=array();
	}
	if($tag=='BLOCKQUOTE') {
		if (strlen($content=$this->BlocContent[$this->ProcessingBloc-1])){
			$this->ProcessingBloc--;
			$this->BlocShow(10,$content,1,$LineFeedHeight);
		}
	}
	if($tag=='TEXTAREA') {
			$this->ProcessingCadre=false;
		if (strlen($content=$this->BlocContent[$this->ProcessingBloc-1])){
			$this->ProcessingBloc--;
			$this->BlocShow(0,$content,1,$LineFeedHeight);
		}
		$this->SetFont('helvetica','',10);
		$this->SetTextColor(0);
	}
	if ($tag==end($this->CurrentTag))
		array_pop($this->CurrentTag);
}

function SetStyle($tag,$enable,$size=0)
{
	static $currentStyle=array();
	if (in_array($tag,array('B','I','U'))){
		if ($enable)
			$currentStyle = array_merge($currentStyle,array($tag=>true));
		else 
			$currentStyle = array_diff($currentStyle,array($tag=>true));
		$family = $this->FontFamily?$this->FontFamily:'helvetica';
		$this->SetFont($family,implode("",array_keys($currentStyle)), $size);
	}
}

// $Link peut être un entier (ID de lien interne)  ou une chaîne (URL)
function PutLink($Link,$Text)
{
	//Place un hyperlien
	$this->SetTextColor(0,0,255);
	$this->SetStyle('U',true);
	$this->Write(5,$Text,$Link);
	$this->SetStyle('U',false);
	$this->SetTextColor(0);
}


function Header()
{
}

/*
Pas utilisé pour l'instant
function Header()
{
    //Imprime l'en-tête du tableau si nécessaire
    if($this->ProcessingTable)
        $this->TableHeader();
}

function TableHeader()
{
    $this->SetFont('helvetica','B',8);
    $this->SetX($this->TableX);
    $fill=!empty($this->HeaderColor);
    if($fill)
        $this->SetFillColor($this->HeaderColor[0],$this->HeaderColor[1],$this->HeaderColor[2]);
    foreach($this->columnProp as $col)
        $this->Cell($column['w'],6,1,0,'C',$fill);
    $this->Ln();
}
*/

function CellSize($htmlContent,$fontFamily,$fontSize,$LineFeedHeight,$cellmargin=3,$max_width=0){
	$cell_pdf=new PDF_SPIP();
	$cell_pdf->Open();
	$cell_pdf->FirstIteration=TRUE;
	$cell_pdf->SetFont($fontFamily, '', $fontSize);
	
	$cell_pdf->ResetBuffer();
	$cell_pdf->maxLineWidth = 0;
	$cell_pdf->x=$cell_pdf->lMargin;
	$cell_pdf->y=0;
	$cell_pdf->CurrentTag = $this->CurrentTag;
	
	if ($max_width){
		$cell_pdf->rMargin=$cell_pdf->w-$cell_pdf->x-$max_width-$cellmargin;
	}
	$cell_pdf -> WriteHTML($htmlContent,$LineFeedHeight);
	if($cell_pdf->x>$cell_pdf->lMargin)
		$cell_pdf->Ln($LineFeedHeight);
	
	$width = $cell_pdf->maxLineWidth-$cell_pdf->lMargin;
	$height = $cell_pdf->y;
	
	$width += $cellmargin;
	$height += $cellmargin;
	return array($width,$height);
}

function OutputCell($width,$height,$htmlContent,$border=0,$LineFeedHeight=0,$align='',$fill=0,$cellmargin=3){
		// dessiner la cellule vide
		$this->Cell($width, $height, '', $border, 0, $align, $fill);
		// on note la position apres la cellule
		$x = $this->x; $y = $this->y;
		$lmargin = $this->lMargin;
		$rmargin = $this->rMargin;
		
		// on se remet en debut de cellule
		$this->x-=$width;
		$this->x = $this->x+$cellmargin/2;
		$this->lMargin = $this->x; // pour que les retour ligne se fassent correctement dans la cellule
		$this->rMargin = $this->w-$this->x-$width+$cellmargin/2; 
		
		$this -> WriteHTML($htmlContent,$LineFeedHeight);
		// on se remet a la fin de la cellule
		$this->x = $x; $this->y = $y;
		$this->lMargin = $lmargin;
		$this->rMargin = $rmargin;
}

function BlocShow($left_margin,$htmlContent,$border=0,$LineFeedHeight){
	$this->Ln($LineFeedHeight);
	$this->x=$this->lMargin+$left_margin;
	$y = $this->y;
	$width = $this->w-$this->rMargin-$this->x;
	list($width2,$height) = $this->CellSize($htmlContent,$this->FontFamily,$this->FontSizePt,$LineFeedHeight,3,$width);
	$this->OutputCell($width,$height,$htmlContent,$border,$LineFeedHeight);
	$this->y = $y+$height;
	$this->Ln($LineFeedHeight);
}

function TableShow($align,$LineFeedHeight)
{
	// Calcul de la taille de police optimale
	// Le calcul ne l'est pas, lui ;-)
	$oldFontSizePt=$this->FontSizePt;
	$oldFontFamily=$this->FontFamily;

	$tableFontFamily='helvetica';
	$cellmargin=3.0;		// pifomètre : un peu de marge sur la largeur de cellule
	$wrwi=$this->w - $this->lMargin - $this->rMargin;
//-----------
	$tableFontSize=10;
	$TableWidth = 1.01*$wrwi;
	$max_width=0;
	$min_font_size=6.0; // mis à 6, 5 était vraiment petit
	$maxiter = 10;
	do {
		$tableFontSize = $tableFontSize *min(1.0,$wrwi/$TableWidth)*0.99; // 0.99 pour converger plus vite
		
		$fixed_width= ($tableFontSize<$min_font_size) || ($maxiter==1) || ($TableWidth<=$wrwi);
		if ($fixed_width)
			$coeff=min(1.0,$wrwi/$TableWidth);
				
		$tableFontSize = max($min_font_size,$tableFontSize);
		// on boucle sur la taille de police tant que la largeur du tableau ne rentre pas dans la page
		
		// remise à zéro des largeurs de colonnes
		foreach ($this->columnProp as $i=>$cprop)
			if ($fixed_width)	$this->columnProp[$i]['w']=$this->columnProp[$i]['w']*$coeff;// redimenssioner la largeur de la colonne
			else	$this->columnProp[$i]['w']=0.0;
		foreach($this->tableContent as $j=>$row)
			$this->lineProp[$j]['h']=0.0;
		
		// on passe toutes les cellules du tableau en revue
		// de façon à calculer la largeur max de chaque colonne pour la taille de police courante
		foreach($this->tableContent as $j=>$row) {
			foreach($row as $i=>$cell) {
				$htmlContent = $cell['content']."<br />";
				if ($this->tableContent[$j][$i]['TH']) {
					$htmlContent="<B>$htmlContent</B>";
				}
				list($width,$height)=$this->CellSize($htmlContent,$tableFontFamily,$tableFontSize,$LineFeedHeight,$cellmargin,$fixed_width?$this->columnProp[$i]['w']:0);
				
				if (!$fixed_width)
					$this->columnProp[$i]['w'] = max($this->columnProp[$i]['w'],$width);
				$this->lineProp[$j]['h'] = max($this->lineProp[$j]['h'],$height)+0.3;
			}
		}
		// Repris de CalcWidth : calcul de la largeur de la table
		$TableWidth=0.0;
		foreach($this->columnProp as $col) {
			$TableWidth += $col['w'];
		}
	} while (!$fixed_width && $maxiter--);

	//-----------
	//	Envoi du tableau dans le flux PDF
	//-----------

	$this->SetFont($tableFontFamily, '', $tableFontSize);
	//Calcule l'abscisse du tableau
	if($align=='C') 
		$this->TableX=max(($this->w-$TableWidth)/2, 0);
	elseif($align=='R')
		$this->TableX=max($this->w-$this->rMargin-$TableWidth, 0);
	else
		$this->TableX=$this->lMargin;

	$ci=0;	# flip-flop pour couleur de fond de ligne
	foreach($this->tableContent as $j=>$row) {
		$this->SetX($this->TableX);
		$fill = !empty($this->RowColors[$ci]);
		if ($fill) {
			$this->SetFillColor($this->RowColors[$ci][0],
								$this->RowColors[$ci][1],
								$this->RowColors[$ci][2]);
		}
		
		foreach($this->tableContent[$j] as $i=>$cell) {
			if ($this->tableContent[$j][$i]['TH'] == true) {
				$this->SetFont($tableFontFamily, 'B', $tableFontSize);
				$this->SetFillColor(255, 255, 0);	// jaune
				$fill=1;
			}
			$this->OutputCell(
				$this->columnProp[$i]['w'],
				$this->lineProp[$j]['h'],
				$cell['content'],
				1,
				$LineFeedHeight,
				$this->columnProp[$i]['a'],
				$fill,0);
			
			if ($this->tableContent[$j][$i]['TH']) {
				$this->SetFont('', '', $tableFontSize);
				$this->SetFillColor(255);	// blanc
			}
		}
		$ci=1-$ci;
		$this->maxLineWidth = max($this->maxLineWidth,$this->x);
		$this->Ln($this->lineProp[$j]['h']);
	}

	$this->SetFont($oldFontFamily, '', $oldFontSizePt);
	$this->Ln($LineFeedHeight);
}
	
// Efface le fichier de dump
function InitDumpFile()
{
	@unlink(DUMP_FILE_FULL_PATH_NAME);
}


// trace une chaîne dans un fichier 
function Dump($String)
{
	if ($f = @fopen(DUMP_FILE_FULL_PATH_NAME,"a"))
    {
		@fwrite($f,$String);
        @fwrite($f,"\n");
		@fclose($f);
    }
}

// trace un tableau dans un fichier 
function DumpArray($String,$Array)
{
	$Result=print_r($Array,true);
	
	if ($f = @fopen(DUMP_FILE_FULL_PATH_NAME,"a"))
    {
		@fwrite($f,$String);
		@fwrite($f,"\n\n");
        if(@fwrite($f,$Result))
        {
            @fclose($f);
        }
    }
	$Array.reset();
}

}	// class PDF extends FPDF

?>
