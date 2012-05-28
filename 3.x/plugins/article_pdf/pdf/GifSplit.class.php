<?php
class GifSplit
{
/*===========================================*/
/*== V A R I A B L E S ==*/
/*===========================================*/
var $gs_image_count = 0;
var $gs_buffer = array();
var $gs_global_data = array();
var $gs_fileframe = array();
var $gs_gif = array(0x47, 0x49, 0x46);
var $gs_header = "\x47\x49\x46\x38\x39\x61"; //GIF89a
var $gs_logical_screen_size;
var $gs_logical_screen_descriptor;
var $gs_global_color_table_size;
var $gs_global_color_table_code;
var $gs_global_color_table_flag;
var $gs_global_image_data;
var $gs_extension_lenght;
var $gs_extension_type;
var $gs_image_descriptor;
var $gs_global_sorted;
var $gs_fin;
var $gs_fou;
var $gs_sp;
var $gs_fm;
var $gs_es;
var $gs_imnbr;
var $gs_rsz;

function GifSplit($image, $format, $name, $max_im_index='0', $resize='1')
{
error_reporting(0);
$this->gs_fm = $format;
$this->gs_sp = $name;
$this->gs_imnbr = ($max_im_index=='')? '-1' : $max_im_index ; // maximal image index (from 0 to n)
$this->gs_rsz = ($resize=='')? '0' : $resize ; //0: no change, 1: resize logical screen size to image size
if ($this->gs_fm != 'GIF') $this->gs_rsz =1;

if($this->gs_fin = fopen($image, "rb"))
{
$this->getbytes(6);
if(!$this->arrcmp($this->gs_buffer, $this->gs_gif, 3))
{
$this->gs_es = "error #1";
return(0);
}
/*étude du Logical Screen Descriptor
      7 6 5 4 3 2 1 0        Field Name                    Type
     +---------------+
  0  |               |       Logical Screen Width          Unsigned
     +-             -+
  1  |               |
     +---------------+
  2  |               |       Logical Screen Height         Unsigned
     +-             -+
  3  |               |
     +---------------+
  4  | |     | |     |       <Packed Fields>               See below
     +---------------+
  5  |               |       Background Color Index        Byte
     +---------------+
  6  |               |       Pixel Aspect Ratio            Byte
     +---------------+
     <Packed Fields>  =      Global Color Table Flag       1 Bit
                             Color Resolution              3 Bits
                             Sort Flag                     1 Bit
                             Size of Global Color Table    3 Bits
*/
//echo "début </br>" ;
$this->getbytes(4);
$this->gs_logical_screen_size = $this->gs_buffer;

//$this->gs_buffer = array();
$this->getbytes(3);
$this->gs_logical_screen_descriptor = $this->gs_buffer;

$this->gs_global_color_table_flag = ($this->gs_buffer[0] & 0x80) ? TRUE : FALSE;
$this->gs_global_color_table_code = ($this->gs_buffer[0] & 0x07);
$this->gs_global_color_table_size = pow(2,($this->gs_global_color_table_code+1));
//$this->gs_global_color_table_size = 2 << $this->gs_global_color_table_code;
$this->gs_global_sorted = ($this->gs_buffer[4] & 0x08) ? TRUE : FALSE;
if($this->gs_global_color_table_flag)
{
$this->getbytes(3 * $this->gs_global_color_table_size);
for($i = 0; $i < ((3 * $this->gs_global_color_table_size)); $i++)
$this->gs_global_data[$i] = $this->gs_buffer[$i];
}


$this->gs_fou = '';

for($loop = true; $loop; )
{
$this->getbytes(1);
switch($this->gs_buffer[0])
{
case 0x21:
$this->read_extension();
break;
case 0x2C:
$this->read_image_descriptor();
break;
case 0x3B:
$loop = false;
break;
default:
$this->gs_es = sprintf("Unrecognized byte code %u\n<br>", $this->gs_buffer[0]); 
}
if (($this->gs_image_count > $this->gs_imnbr)and($this->gs_imnbr > -1))
{
$loop = false;
}

}
fclose($this->gs_fin);
}
else
{
$this->gs_es = "error #2";
return(0);
}
$this->gs_es = "ok";

}
/*///////////////////////////////////////////////*/
/*// Function :: read_extension() //*/
/*///////////////////////////////////////////////*/
function read_extension()
{
/* Reset global variables */
/* 0x21 puis 4 bytes discriminants: 
  0xF9 : Graphic Control Extension puis 3ème byte Block Size
  0xFE : Comment Extension
  0x01 : Plain Text Extension puis 3ème byte Block Size
  0xFF : Application Extension puis 3ème byte Block Size
      7 6 5 4 3 2 1 0        Field Name                    Type
     +---------------+
  0  |     0x21      |       
     +-             -+
  1  |     ????      |    |  0xF9  | 0xFE  |  0x01  |
     +---------------+
  2  |               |    | Taille | data  | Taille |
     +-             -+
  3  |               |    |  Data  | Data  |  Data  |
     |               |      
     |               |       
     +---------------+
     |     0x00      |     Block Terminator  
     +---------------+

      7 6 5 4 3 2 1 0        Field Name                  
     +---------------+
  0  |     0x21      |       
     +-             -+
  1  |     0xFF      |      
     +---------------+
  2  |     Taille    |      Taille 1ère extension
     +-             -+
  3  |               |        
     |               |        Data
     |               |        
     +-             -+
     |     Taille    |       Taille 2ème extension (structure à confirmer)
     +-             -+
     |               |        
     |               |        Data
     |               |      
     +---------------+
     |     0x00      |       Block Terminator
     +---------------+

*/  
$this->gs_fou .="\x21";
$this->gs_buffer = array();
$this->getbytes(2);
$this->putbytes($this->gs_buffer, 2);
$this->gs_extension_type = $this->gs_buffer[0];
$this->gs_extension_lenght=$this->gs_buffer[1]; 
if (array_search($this->gs_extension_type, array (1=>0xF9,2=>0x01,3=>0xFF)))
{
$this->getbytes($this->gs_extension_lenght);
$this->putbytes($this->gs_buffer, $this->gs_extension_lenght);
if ($this->gs_extension_type == 0xFF)
  {
 	$this->getbytes(1);
 	$this->putbytes($this->gs_buffer, 1);
  $this->gs_extension_lenght=$this->gs_buffer[0]; 
 	$this->getbytes($this->gs_extension_lenght);
  $this->putbytes($this->gs_buffer, $this->gs_extension_lenght);
  }
}
 for(;;)
  {
 	$this->getbytes(1);
  $this->putbytes($this->gs_buffer, 1);
// byte == 0 : fin du data de l'extension
  if ($this->gs_buffer[0] == 0)
  {
  break ;
  }
 }
 

}

/*///////////////////////////////////////////////*/
/*// Function :: read_image_descriptor() //*/
/*///////////////////////////////////////////////*/
function read_image_descriptor()
{
/* Reset global variables */
$this->gs_buffer = array();

//Lecture du descripteur de l'image: Image Descriptor
/*
      7 6 5 4 3 2 1 0        Field Name                    Type
     +---------------+
  0  |     0x2C      |       Image Separator               Byte
     +---------------+
  1  |               |       Image Left Position           Unsigned
     +-             -+
  2  |               |
     +---------------+
  3  |               |       Image Top Position            Unsigned
     +-             -+
  4  |               |
     +---------------+
  5  |               |       Image Width                   Unsigned
     +-             -+
  6  |               |
     +---------------+
  7  |               |       Image Height                  Unsigned
     +-             -+
  8  |               |
     +---------------+
  9  | | | |0 0|     |       <Packed Fields>               See below
     +---------------+
     <Packed Fields>  =      Local Color Table Flag        1 Bit
                             Interlace Flag                1 Bit
                             Sort Flag                     1 Bit
                             Reserved                      2 Bits
                             Size of Local Color Table     3 Bits
*/

$this->gs_fou .="\x2C";

$this->getbytes(9);
for($i = 0; $i < 9; $i++)
{
$this->gs_image_descriptor[$i] = $this->gs_buffer[$i];
}

if ($this->gs_rsz==1) // new screen sizes and image edges
{
// new logical screen size
$this->gs_logical_screen_size[0] = $this->gs_image_descriptor[4];
$this->gs_logical_screen_size[1] = $this->gs_image_descriptor[5];
$this->gs_logical_screen_size[2] = $this->gs_image_descriptor[6];
$this->gs_logical_screen_size[3] = $this->gs_image_descriptor[7];
// reset position
$this->gs_image_descriptor[0] = $this->gs_image_descriptor[1] = $this->gs_image_descriptor[2] = $this->gs_image_descriptor[3] = 0;
}
$this->putbytes($this->gs_image_descriptor, 9);

$local_color_table_flag = ($this->gs_buffer[8] & 0x80) ? TRUE : FALSE;

if($local_color_table_flag)
{
//il y a une table locale des couleurs
$code = ($this->gs_buffer[8] & 0x07);
$sorted = ($this->gs_buffer[8] & 0x20) ? TRUE : FALSE;
$size = pow(2,($code+1));
}

if($local_color_table_flag)
{
$this->getbytes(3 * $size);
$this->putbytes($this->gs_buffer, 3 * $size);
}
/* LZW minimum code size */
$this->getbytes(1);
$this->putbytes($this->gs_buffer, 1);

/* Image Data */
for(;;)
{
$this->getbytes(1);
$this->putbytes($this->gs_buffer, 1);
if(($u = $this->gs_buffer[0]) == 0)
break;
$this->getbytes($u);
$this->putbytes($this->gs_buffer, $u);
}

$this->gs_global_image_data = $this->gs_fou;

//Construction de la structure de tête du fichier

// Header -> GIF89a //
$this->gs_fou = $this->gs_header;

//logical_screen_descriptor//
$this->putbytes($this->gs_logical_screen_size,4);
$this->putbytes($this->gs_logical_screen_descriptor,3);

//Global Color Table//
$this->putbytes($this->gs_global_data, $this->gs_global_color_table_size*3);

//Global_image_data

$this->gs_fou .= $this->gs_global_image_data;

/* trailer */
$this->gs_fou .= "\x3B";




/* Write to file */
switch($this->gs_fm)
{
case "GIF":
//Enregistrement du fichier gif
$framename = $this->gs_sp . $this->gs_image_count . ".gif";
 if (!$handle = fopen($framename, 'w')) {
         echo "Impossible d'ouvrir le fichier ($framename)";
         exit;
    }

if(!fwrite($handle,$this->gs_fou))
{
$this->gs_es = "error #3";
return(0);
}

$this->gs_fileframe[]=$framename;
break;
/* Write as BMP */
case "BMP":
$im = imageCreateFromString($this->gs_fou);
$framename = $this->gs_sp . $this->gs_image_count . ".bmp";
if(!$this->imageBmp($im, $framename))
{
$this->gs_es = "error #3";
return(0);
}
imageDestroy($im);
break;
/* Write as PNG */
case "PNG":
$im = imageCreateFromString($this->gs_fou);
$framename = $this->gs_sp . $this->gs_image_count . ".png";
if(!imagePng($im, $framename))
{
$this->gs_es = "error #3";
return(0);
}
imageDestroy($im);
break;
/* Write as JPG */
case "JPG":
$im = imageCreateFromString($this->gs_fou);
$framename = $this->gs_sp . $this->gs_image_count . ".jpg";
if(!imageJpeg($im, $framename))
{
$this->gs_es = "error #3";
return(0);
}
imageDestroy($im);
break;
/* Write as GIF */
case "GIF":
$im = imageCreateFromString($this->gs_fou);

imageDestroy($im);

break;
}
$this->gs_image_count++;
$this->gs_fou = '';
}
/*///////////////////////////////////////////////*/
/*// BMP creation group //*/
/*///////////////////////////////////////////////*/
/* ImageBMP */
function imageBmp($img, $file, $RLE=0)
{
$ColorCount = imagecolorstotal($img);
$Transparent = imagecolortransparent($img);
$IsTransparent = $Transparent != -1;
if($IsTransparent)
$ColorCount--;
if($ColorCount == 0)
{
$ColorCount = 0;
$BitCount = 24;
}
if(($ColorCount > 0) && ($ColorCount <= 2))
{
$ColorCount = 2;
$BitCount = 1;
}
if(($ColorCount > 2) && ($ColorCount <= 16))
{
$ColorCount = 16;
$BitCount = 4;
}
if(($ColorCount > 16) && ($ColorCount <= 256))
{
$ColorCount = 0;
$BitCount = 8;
}
$Width = imageSX($img);
$Height = imageSY($img);
$Zbytek = (4 - ($Width / (8 / $BitCount)) % 4) % 4;
if($BitCount < 24)
$palsize = pow(2, $BitCount) * 4;
$size = (floor($Width / (8 / $BitCount)) + $Zbytek) * $Height + 54;
$size += $palsize;
$offset = 54 + $palsize;
// Bitmap File Header
$ret = 'BM';
$ret .= $this->int_to_dword($size);
$ret .= $this->int_to_dword(0);
$ret .= $this->int_to_dword($offset);
// Bitmap Info Header
$ret .= $this->int_to_dword(40);
$ret .= $this->int_to_dword($Width);
$ret .= $this->int_to_dword($Height);
$ret .= $this->int_to_word(1);
$ret .= $this->int_to_word($BitCount);
$ret .= $this->int_to_dword($RLE);
$ret .= $this->int_to_dword(0);
$ret .= $this->int_to_dword(0);
$ret .= $this->int_to_dword(0);
$ret .= $this->int_to_dword(0);
$ret .= $this->int_to_dword(0);
// image data
$CC = $ColorCount;
$sl1 = strlen($ret);
if($CC == 0)
$CC = 256;
if($BitCount < 24)
{
$ColorTotal = imagecolorstotal($img);
if($IsTransparent)
$ColorTotal--;
for($p = 0; $p < $ColorTotal; $p++)
{
$color = imagecolorsforindex($img, $p);
$ret .= $this->inttobyte($color["blue"]);
$ret .= $this->inttobyte($color["green"]);
$ret .= $this->inttobyte($color["red"]);
$ret .= $this->inttobyte(0);
}
$CT = $ColorTotal;
for($p = $ColorTotal; $p < $CC; $p++)
{
$ret .= $this->inttobyte(0);
$ret .= $this->inttobyte(0);
$ret .= $this->inttobyte(0);
$ret .= $this->inttobyte(0);
}
}
if($BitCount <= 8)
{
for($y = $Height - 1; $y >= 0; $y--)
{
$bWrite = "";
for($x = 0; $x < $Width; $x++)
{
$color = imagecolorat($img, $x, $y);
$bWrite .= $this->decbinx($color, $BitCount);
if(strlen($bWrite) == 8)
{
$retd .= $this->inttobyte(bindec($bWrite));
$bWrite = "";
}
}
if((strlen($bWrite) < 8) and (strlen($bWrite) != 0))
{
$sl = strlen($bWrite);
for($t = 0; $t < 8 - $sl; $t++)
$sl .= "0";
$retd .= $this->inttobyte(bindec($bWrite));
}
for($z = 0; $z < $Zbytek; $z++)
$retd .= $this->inttobyte(0);
}
}
if(($RLE == 1) and ($BitCount == 8))
{
for($t = 0; $t < strlen($retd); $t += 4)
{
if($t != 0)
if(($t) % $Width == 0)
$ret .= chr(0).chr(0);
if(($t + 5) % $Width == 0)
{
$ret .= chr(0).chr(5).substr($retd, $t, 5).chr(0);
$t += 1;
}
if(($t + 6) % $Width == 0)
{
$ret .= chr(0).chr(6).substr($retd, $t, 6);
$t += 2;
}
else
$ret .= chr(0).chr(4).substr($retd, $t, 4);
}
$ret .= chr(0).chr(1);
}
else
$ret .= $retd;
if($BitCount == 24)
{
for($z = 0; $z < $Zbytek; $z++)
$Dopl .= chr(0);
for($y = $Height - 1; $y >= 0; $y--)
{
for($x = 0; $x < $Width; $x++)
{
$color = imagecolorsforindex($img, ImageColorAt($img, $x, $y));
$ret .= chr($color["blue"]).chr($color["green"]).chr($color["red"]);
}
$ret .= $Dopl;
}
}
if(fwrite(fopen($file, "wb"), $ret))
return true;
else
return false;
}
/* INT 2 WORD */
function int_to_word($n)
{
return chr($n & 255).chr(($n >> 8) & 255);
}
/* INT 2 DWORD */
function int_to_dword($n)
{
return chr($n & 255).chr(($n >> 8) & 255).chr(($n >> 16) & 255).chr(($n >> 24)
& 255); }
/* INT 2 BYTE */
function inttobyte($n)
{
return chr($n);
}
/* DECIMAL 2 BIN */
function decbinx($d,$n)
{
$bin = decbin($d);
$sbin = strlen($bin);
for($j = 0; $j < $n - $sbin; $j++)
$bin = "0$bin";
return $bin;
}
/*///////////////////////////////////////////////*/
/*// Function :: arrcmp() //*/
/*///////////////////////////////////////////////*/
function arrcmp($b, $s, $l)
{
for($i = 0; $i < $l; $i++)
{
if($s{$i} != $b{$i}) return false;
}
return true;
}
/*///////////////////////////////////////////////*/
/*// Function :: getbytes() //*/
/*///////////////////////////////////////////////*/
function getbytes($l)
{
for($i = 0; $i < $l; $i++)
{
$bin = unpack('C*', fread($this->gs_fin, 1));
$this->gs_buffer[$i] = $bin[1];
}
return $this->gs_buffer;
}
/*///////////////////////////////////////////////*/
/*//           Function :: putbytes()          //*/
/*///////////////////////////////////////////////*/
function putbytes($s, $l)
{
for($i = 0; $i < $l; $i++)
{
$this->gs_fou .= pack('C*', $s[$i]);
}
}
function getFilelist()
{
return $this->gs_fileframe;
}

function getReport()
{
return $this->gs_es;
}
}
?>
