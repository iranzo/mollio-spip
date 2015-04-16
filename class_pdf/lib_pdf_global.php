<?php
/**
 * class PDF extends FPDF : FPDF/tutoriel/tuto6.htm
 * 
 * Février-Août 2003 : Jérôme Fenal (jerome.fenal@logicacmg.com)
 * Ajout de la prise en compte des tableaux, tag <code>, et diverses autres choses de SPIP
 */ 


class PDF extends FPDF
{
var $B;
var $I;
var $U;
var $HREF;
var $SRC;
var $columnProp=array();		# propriétés de la ligne
var $inFirstRow;		# flag si première ligne en cours
var $TableX;			# abscisse du tableau
var $HeaderColor;
var $RowColors;
var $tableProp=array();
var $ProcessingTable=false;	# =1 : en cours lecture table
var $ProcessingTDTH=false;	# =1 : en cours lecture cellule tableau
var $ProcessingTH=false;	# =1 : en cours lecture cellule tableau heading
var $ProcessingCadre=false;	# =1 : en cours lecture contenu d'un cadre SPIP (TEXTAREA HTML)
var $tableCurrentCol;	# numéro de cellule courante
var $tableCurrentRow;	# Numero de ligne courante pendant la lecture d'un tableau
var $tableContent=array();		# Contenu de la table courante pendant son absorption. Non réentrant car SPIP ne permet pas de faire
						# de table dans une autre table.
var $listDepth=0;		# profondeur courante de liste à puce
var $listParm = array();	# paramètres des listes à puces en fonction du niveau
var $debug=0;

function AddCol($field=-1,$width=-1,$align='L')
{
    //Ajoute une colonne au tableau
    if($field==-1)
        $field=count($this->columnProp);
    $this->columnProp[$field]=array('f'=>$field,'w'=>$width,'a'=>$align);
#$this->Write(5, "Ajout de colonne : ".$field."/".$width."/".$align); $this->Ln();
}


function PDF($orientation='P', $unit='mm', $format='A4')
{
	//Appel au constructeur parent
	$this->FPDF($orientation, $unit, $format);
	$this->SetCompression(1);
	//Initialisation
	$this->B=0;
	$this->I=0;
	$this->U=0;
	$this->HREF='';
}

function unhtmlentities($string)
{
	$trans_tbl = get_html_translation_table (HTML_ENTITIES);
	$trans_tbl = array_flip ($trans_tbl);
	$ret = strtr ($string, $trans_tbl);
	return preg_replace('/&#(\d+);/me', "chr('\\1')",$ret);
}

function WriteHTML($html)
{
	//Parseur HTML
	$html=str_replace("\n",' ',$html);
	$html=$this->unhtmlentities($html);
	$a=preg_split('/<(.*)>/U', $html, i-1, PREG_SPLIT_DELIM_CAPTURE);
	foreach($a as $i=>$e) {
		if($i%2==0) {
			//Texte
			# Attention, ce mécanisme ne permet pas de traiter les liens dans les tableaux...
			# ni les tableaux dans les tableaux, d'ailleurs...
			if ($this->ProcessingTDTH) {
				# tableCurrentCol - 1 car tableCurrentCol déjà incrémenté.
				$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['content'] .= $e;
				if ($this->ProcessingTH) {
					$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['TH']=1;
				} else {
					$this->tableContent[$this->tableCurrentRow][$this->tableCurrentCol - 1]['TH']=0;
				}
			} else {
				if($this->HREF) {
					$this->PutLink($this->HREF, $e);
				} else {
					$this->Write(5,$e);
				}
			}
		} else {
			//Balise
			if($e{0}=='/') {
				$this->CloseTag(strtoupper(substr($e,1)));
			} else {
				//Extraction des propriétés
				$a2=split(' ',$e);
				$tag=strtoupper(array_shift($a2));
				$this->prop=array();
				foreach($a2 as $v)
					if (ereg('^([^=]*)=["\']?([^"\']*)["\']?$',$v,$a3))
						$this->prop[strtoupper($a3[1])]=$a3[2];
				$this->OpenTag($tag,$this->prop);
			}
		}
	}
}

function OpenTag($tag,$prop)
{
	//Balise ouvrante
	if($tag=='B' or $tag=='I' or $tag=='U') {
		$this->SetStyle($tag,true);
	}

	if($tag=='A') {
		$this->HREF=$this->prop['HREF'];
	}

	if($tag=='BR') {
		$this->Ln(5);
	}

	if($tag=='P') {
		$this->Ln(5);
	}

	if($tag=='CODE') {
		$this->Write(5,'<code>');
	}
	
	if($tag=='H3') {
		$this->Ln(15);
		// $this->$align = "C";
		$this->SetStyle($tag='B',true,14);
	}

	if($tag=='UL' or $tag=='OL') {
		$this->SetLeftMargin($this->lMargin+7);
		$this->listDepth++;
		$this->listParm[$this->listDepth]['type']=$tag;
		$this->listParm[$this->listDepth]['curr']=0;		# numéro si OL
	}

	if($tag=='LI'){ 
		$this->Ln();
		$this->listParm[$this->listDepth]['curr']++;
		$this->SetX($this->GetX()-7);
		if ($this->listParm[$this->listDepth]['type']=='OL')
			$this->Cell(7,5,$this->listParm[$this->listDepth]['curr'].'.',0,0,'C'); 
		else
			$this->Cell(7,5,chr(149),0,0,'C'); 
	}

	if ($tag=='IMG') {
		$this->SRC=$this->prop['SRC'];
		$size=getimagesize($this->SRC);		# Attention, utilisation de GD !!! FPDF ne sait pas lire les images à moitié... et je n'ai pas envie de surcharger la méthode Image...
		if ($size[0] < 30 && $size[1] < 30) {
			# pixel / 3 pour avoir des cm. Petite cuisine...
			$imgX=$size[0]/3;
			$imgY=$size[1]/3;
			$yoffset=$imgY/4;
			if ($this->GetY() + $imgY > $this->h - $this->bMargin)
				$this->AddPage();
			$this->Image($this->SRC, $this->GetX(), $this->GetY()-$yoffset, $imgX, $imgY);
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
			$this->Image($this->SRC, $this->GetX()+($pwidth-$imgX)/2, $this->GetY(), $imgX, $imgY);
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

			$this->Image($this->SRC, $this->GetX()+($pwidth-$imgX)/2, $this->GetY(), $imgX, $imgY);
			$this->SetY($this->GetY()+$imgY);
		}
	}

	if($tag=='TT' or $tag=='TEXTAREA') {
		$this->SetFont('courier','', 8);
		$this->SetTextColor(255, 0, 0);
		if ($tag=='TEXTAREA')
			$this->ProcessingCadre=true;
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
		if ($prop['CLASS'] == 'row_first') {
			$this->ProcessingTH=true;
		}
	}

	if($tag=='TH' or $tag=='TD') {
		# Cellule (pas titre)
		$this->tableCurrentCol += 1;
		if ($this->inFirstRow) {
			$this->nCols=$this->tableCurrentCol;
			$this->AddCol();
		}
		$this->ProcessingTDTH=true;
	}
//	if($tag=='HR') {
		# Ligne horizontale
	//	$this->SetLineWidth(0.0);
	//	$this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
	//}
}

function CloseTag($tag)
{
	//Balise fermante
	if($tag=='B' or $tag=='I' or $tag=='U'){
		$this->SetStyle($tag,false);
	}
		
	if($tag=='A'){
		$this->HREF='';
	}
		
	if($tag=='P'){
		$this->Ln(5);
	}

	if($tag=='CODE') {
		$this->Write(5,'</code>');
	}

	if($tag=='H3'){		
		$this->SetStyle($tag='B',false,10);
		$this->Ln(5);
	}
	
	if($tag=='UL' or $tag=='OL') { 
		$this->SetLeftMargin($this->lMargin-7); 
		$this->Ln();
		$this->listParm[$this->listDepth]=array();
		$this->listDepth--;
	} 
	if($tag=='TT' or $tag=='TEXTAREA') { 
		$this->SetFont('helvetica','',10);
		$this->SetTextColor(0);
		if ($tag=='TEXTAREA')
			$this->ProcessingCadre=false;
	}
	if($tag=='TD' or $tag=='TH') {
		$this->ProcessingTDTH=false;
	}
	if($tag=='TR') {
		$this->inFirstRow=0;	# on a fini une ligne donc la première aussi
		$this->ProcessingTH=false;
	}

	if($tag=='TABLE') {
		$this->TableShow('C');
		$this->inFirstRow=0;
		$this->ProcessingTable=false;
		$this->cMargin=$cMargin;
		$this->columnProp=array();
		$this->tableContent=array();
	}
}

function SetStyle($tag,$enable,$size=0)
{
	//Modifie le style et sélectionne la police correspondante
	$this->$tag+=($enable ? 1 : -1);
	$style='';
	foreach(array('B','I','U') as $s)
		if($this->$s > 0)
			$style.=$s;
	if ($size==0)
		$this->SetFont('',$style);
	else
		$this->SetFont('',$style, $size);
}

function PutLink($URL,$txt)
{
	//Place un hyperlien
	$this->SetTextColor(0,0,255);
	$this->SetStyle('U',true);
	$this->Write(5,$txt,$URL);
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

function TableShow($align)
{

	// Calcul de la taille de police optimale
	// Le calcul ne l'est pas, lui ;-)
	$oldFontSizePt=$this->FontSizePt;
	$oldFontFamily=$this->FontFamily;

	$tableFontFamily='helvetica';
	$cellmargin=3.0;		// pifomètre : un peu de marge sur la largeur de cellule
	$wrwi=$this->w - $this->lMargin - $this->rMargin;
//-----------
	$tableFontSize=10.0;
	do {
		$tableFontSize = $tableFontSize - 1.0;
		// on boucle sur la taille de police tant que la largeur du tableau ne rentre pas dans la page

		$this->SetFont($tableFontFamily, '', $tableFontSize);

		// remise à zéro des largeurs de colonnes
		foreach ($this->columnProp as $i=>$cprop) {
			$this->columnProp[$i]['w']=0.0;
		}
		
		// on passe toutes les cellules du tableau en revue
		// de façon à calculer la largeur max de chaque colonne pour la taille de police courante
		foreach($this->tableContent as $j=>$row) {
			foreach($row as $i=>$cell) {
				if ($this->tableContent[$j][$i]['TH']) {
					$this->SetFont($tableFontFamily, 'B', $tableFontSize);
				}
				$len = $this->GetStringWidth($cell['content']);
				$len += $cellmargin;
				if ($len > $this->columnProp[$i]['w']) {
					// max...
					$this->columnProp[$i]['w'] = $len;
				}
				$this->SetFont($tableFontFamily, '', $tableFontSize);
			}
		}
// Repris de CalcWidth : calcul de la largeur de la table
	    $TableWidth=0.0;
		foreach($this->columnProp as $col) {
			$TableWidth += $col['w'];
		}
	} while ($TableWidth > $wrwi && $tableFontSize > 1.0);


//-----------
//	Envoi du tableau dans le flux PDF
//-----------

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
//		print("Cellule : [".$cell."], \$i=".$i."  largeur : ".$this->columnProp[$i]['w']."<BR>");
			if ($this->tableContent[$j][$i]['TH'] == true) {
				$this->SetFont($tableFontFamily, 'B', $tableFontSize);
				$this->SetFillColor(255, 255, 0);	// jaune
				$fill=1;
			}
			$this->Cell($this->columnProp[$i]['w'], 5, $cell['content'], 1, 0, $this->columnProp[$i]['a'], $fill);	
			if ($this->tableContent[$j][$i]['TH']) {
				$this->SetFont('', '', $tableFontSize);
				$this->SetFillColor(255);	// blanc
			}
		}
		$ci=1-$ci;
		$this->Ln();
	}

	$this->SetFont($oldFontFamily, '', $oldFontSizePt);
}

}

?>
