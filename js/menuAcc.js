// Menu accessible dynamique et CSS alternatives, V 1.0
//
// Copyright (c) 2004 Jacques PYRAT
// http://www.pyrat.net/
//
// Licensed under the LGPL license
// http://www.gnu.org/copyleft/lesser.html
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************
//
// Presets
var jp_blankpic='images/header/spacer.gif';
var jp_onpic='images/menu/plus.gif';
var jp_offpic='images/menu/minus.gif';
var jp_picalt='';
var jp_strDeplier='';
var jp_strReplier='';
var jp_parentID='nav-secondary'; // identifiant du menu. Si vide, toutes les listes de lien de la page sont traitées
// Checking for DOM compatibility	
if (document.getElementById && document.createTextNode && document.createElement){jp_canDOM=true}

function jp_expinit(){
	if (jp_canDOM){
		if(jp_parentID && document.getElementById(jp_parentID)){
			jp_alluls=document.getElementById(jp_parentID).getElementsByTagName('UL');
			jp_alllis=document.getElementById(jp_parentID).getElementsByTagName('LI');
		}else{
			jp_alluls=document.getElementsByTagName('UL');
			jp_alllis=document.getElementsByTagName('LI');
		}
		for(i=0;i<jp_alllis.length;i++){
			jp_islink=jp_alllis[i].getElementsByTagName('A')[0];
			if(jp_islink){
				jp_addimg = document.createElement('img');
				jp_addimg.src=jp_blankpic;
				jp_addimg.className='node';
				jp_addimg.alt='';
				jp_addimg.onclick=function() {jp_ex(this,null);return false;};
				jp_alllis[i].getElementsByTagName('A')[0].onkeypress=inputKeyHandler;
				jp_alllis[i].getElementsByTagName('A')[0].onfocus=function() {jp_ex(this,0);};
				jp_alllis[i].insertBefore(jp_addimg,jp_alllis[i].firstChild);
			}
		}

		for(i=0;i<jp_alluls.length;i++){
			jp_subul=jp_alluls[i];
			if(jp_subul.parentNode.tagName=='LI'){
				// Do not collapse when there is a strong element in the list.
				jp_highlight=jp_subul.parentNode.getElementsByTagName('strong').length==0?true:false;
				jp_disp=jp_highlight?'none':'block';
				var jp_picaltonoff=jp_highlight?jp_picalt+jp_strDeplier:jp_picalt+jp_strReplier;
				jp_pic=jp_highlight?jp_onpic:jp_offpic;
				// End  highlight change
				jp_childs=jp_subul.getElementsByTagName('LI').length
				jp_momimg=jp_subul.parentNode.getElementsByTagName('IMG')[0]
				if(jp_momimg){
					jp_momimg.setAttribute('title',jp_picaltonoff+jp_subul.parentNode.getElementsByTagName('A')[0].innerHTML);
					jp_momimg.setAttribute('alt',jp_picaltonoff+jp_subul.parentNode.getElementsByTagName('A')[0].innerHTML);
					jp_momimg.src=jp_pic;
					jp_subul.style.display=jp_disp;
				}
			}
		}

		// Suppresion des images qui ne sont pas des plus ou moins
		for(i=0;i<jp_alllis.length;i++){
			jp_momimg=jp_alllis[i].getElementsByTagName('IMG')[0];
			if(jp_momimg){
				if (jp_momimg.src.indexOf(jp_blankpic)!=-1) {
					// remove link and image
					jp_momimg.parentNode.getElementsByTagName('A')[0].onfocus=function() {return true;};
					jp_momimg.parentNode.getElementsByTagName('A')[0].onkeypress=function() {return true;};
					jp_momimg.parentNode.removeChild(jp_momimg);
				}					
			}
		}
	}
}

// Collapse and Expand node.
function jp_ex(jp_n,jp_event){
	if(jp_canDOM){
		jp_u=jp_n.parentNode.getElementsByTagName("ul")[0];
		if (!jp_u) jp_u=jp_n.parentNode.parentNode.getElementsByTagName("ul")[0];;
		if(jp_u){
			if (((jp_u.style.display=='none'||jp_u.style.display=='')&&(jp_event==0||jp_event==43||jp_event==null))||((jp_u.style.display=='block')&&(jp_event==45||jp_event==null))) {
				jp_u.style.display=jp_u.style.display=='none'||jp_u.style.display==''?'block':'none';
				jp_img=jp_u.parentNode.getElementsByTagName('img')[0];
				jp_img.src=jp_img.src.indexOf(jp_offpic)!=-1?jp_onpic:jp_offpic;
				var jp_strAlt=jp_img.getAttribute('title');
				if (jp_img.src.indexOf(jp_offpic)!=-1) {
					var jp_re = new RegExp (jp_strDeplier, 'gi');
					var jp_strAltNew = jp_strAlt.replace(jp_re,jp_strReplier);
				}else{
					var jp_re = new RegExp (jp_strReplier, 'gi');
					var jp_strAltNew = jp_strAlt.replace(jp_re,jp_strDeplier);
				}
				jp_img.setAttribute('title',jp_strAltNew);
				jp_img.setAttribute('alt',jp_strAltNew);
				return true;
			} else {
				return false;
			}
		}
	}			
}
function inputKeyHandler(ev) {
	ev = ev || event;
	if (jp_ex(ev.target || ev.srcElement,ev.keyCode || ev.which)) {
		ev.cancelBubble= true;
		if (ev.stopPropagation) ev.stopPropagation();
	}
}

window.onload = function()
{
  jp_expinit(); // Doit être placé *après* le menu concerné!!!
}
