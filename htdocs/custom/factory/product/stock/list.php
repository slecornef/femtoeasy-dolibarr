<?php
/* Copyright (C) 2001-2004	Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003		Eric Seigne			<erics@rycks.com>
 * Copyright (C) 2004-2012	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2014-2022	Charlene BENKE		<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *		\file	   htdocs/factory/product/list.php
 *	  \ingroup	factory
 *		\brief	  Page to list all factory process
 */

$res=@include("../../../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."../../main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."../../main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT."/core/lib/stock.lib.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$key=GETPOST('key');
$parent=GETPOST('parent');

// Security check
if (! empty($user->socid)) $socid=$user->socid;
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
$result=restrictedArea($user, 'factory');

$mesg = '';

$product = new Product($db);
$factory = new Factory($db);
$object = new Entrepot($db);

$form=new Form($db);

$productid=0;
if ($id || $ref) {
	$result = $object->fetch($id, $ref);
	$productid=$object->id;
	$id=$object->id;
	$factory->id =$id;
}

$langs->load("bills");
$langs->load("products");
$langs->load("factory@factory");
$langs->load("stocks");

$search_status	= GETPOST("fk_status", 'int');

$sall=GETPOST("contactname");
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page')?GETPOST('page', 'int'):0;
$userid=GETPOST('userid', 'int');
$begin=GETPOST('begin');
$search_ref=GETPOST('search_ref', 'alpha');

if (! $sortorder) $sortorder="DESC";
if (! $sortfield) $sortfield="f.date_end_planned";
$limit = $conf->liste_limit;
$offset = $limit * $page;


if (GETPOST('button_removefilter')) {
	$search_lastname="";
	$search_firstname="";
	$search_societe="";
	$search_poste="";
	$search_phone="";
	$search_phoneper="";
	$search_phonepro="";
	$search_phonemob="";
	$search_fax="";
	$search_email="";
	$search_skype="";
	$search_priv="";
	$sall="";
	$seach_status=-1;
}


llxHeader("", "", $langs->trans("CardProduct".$product->type));

dol_htmloutput_mesg($mesg);

$head=stock_prepare_head($object, $user);
$titre=$langs->trans("Warehouse");
$picto=('stock');
dol_fiche_head($head, 'factory', $titre, 0, $picto);
$linkback = '<a href="'.DOL_URL_ROOT.'/product/stock/list.php">'.$langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';
$morehtmlref.=$langs->trans("LocationSummary").' : '.$object->lieu;
$morehtmlref.='</div>';

dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'libelle', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

// Parent entrepot
$parentwarehouse = new Entrepot($db);
if (!empty($object->fk_parent) && $parentwarehouse->fetch($object->fk_parent) > 0) {
	print '<tr><td>'.$langs->trans("ParentWarehouse").'</td><td>';
	print $parentwarehouse->getNomUrl(3);
	print '</td></tr>';
}

// Description
print '<tr><td class="titlefield tdtop">'.$langs->trans("Description").'</td><td>'.nl2br($object->description).'</td></tr>';

$calcproductsunique = $object->nb_different_products();
$calcproducts = $object->nb_products();

// Total nb of different products
print '<tr><td>'.$langs->trans("NumberOfDifferentProducts").'</td><td>';
print empty($calcproductsunique['nb']) ? '0' : $calcproductsunique['nb'];
print "</td></tr>";

// Nb of products
print '<tr><td>'.$langs->trans("NumberOfProducts").'</td><td>';
$valtoshow = price2num($calcproducts['nb'], 'MS');
print empty($valtoshow) ? '0' : $valtoshow;
print "</td></tr>";

print '</table>';

print '</div>';
print '<div class="fichehalfright">';
print '<div class="ficheaddleft">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

// Value
print '<tr><td class="titlefield">'.$langs->trans("EstimatedStockValueShort").'</td><td>';
print price((empty($calcproducts['value']) ? '0' : price2num($calcproducts['value'], 'MT')), 0, $langs, 0, -1, -1, $conf->currency);
print "</td></tr>";

// Last movement
if (!empty($user->rights->stock->mouvement->lire)) {
	$sql = "SELECT max(m.datem) as datem";
	$sql .= " FROM ".MAIN_DB_PREFIX."stock_mouvement as m";
	$sql .= " WHERE m.fk_entrepot = '".$object->id."'";
	$resqlbis = $db->query($sql);
	if ($resqlbis) {
		$obj = $db->fetch_object($resqlbis);
		$lastmovementdate = $db->jdate($obj->datem);
	} else {
		dol_print_error($db);
	}
	print '<tr><td>'.$langs->trans("LastMovement").'</td><td>';
	if ($lastmovementdate) {
		print dol_print_date($lastmovementdate, 'dayhour').' ';
		print '(<a href="'.DOL_URL_ROOT.'/product/stock/movement_list.php?id='.$object->id.'">'.$langs->trans("FullList").'</a>)';
	} else {
		print $langs->trans("None");
	}
	print "</td></tr>";
}

// Other attributes
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

// Categories
if (!empty($conf->categorie->enabled)) {
	print '<tr><td valign="middle">'.$langs->trans("Categories").'</td><td colspan="3">';
	print $form->showCategories($object->id, Categorie::TYPE_WAREHOUSE, 1);
	print "</td></tr>";
}

print "</table>";

print '</div>';
print '</div>';
print '</div>';

print '<div class="clearboth"></div>';

dol_fiche_end();



print_titre($langs->trans("OrderBuildList"));

$sql = "SELECT *";
$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
$sql.= ' WHERE f.fk_entrepot='.$id;
$sql.= ' AND   f.fk_statut != 2';
switch ($search_status) {
	case 1:
		$sql .= " AND f.date_end_made is null";
		break;
	case 2:
		$sql .= " AND f.date_end_made is not null";
		break;
}	 

// Count total nb of records
$nbtotalofrecords = 0;
$sql.= " ORDER BY $sortfield $sortorder ";
$sql.= " ".$db->plimit($conf->liste_limit+1, $offset);

//print $sql;
dol_syslog("factory/product/list.php sql=".$sql);
$result = $db->query($sql);
//print $sql;
if ($result) {
	$param ='&begin='.urlencode($begin).'&userid='.urlencode($userid);
	if (!empty($search_categ)) 
		$param.='&search_categ='.$search_categ;
	if ($search_status != '') 
		$param.='&search_status='.$search_status;

	$num = $db->num_rows($result);
	$i = 0;

	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';

	print '<table class="liste" width="100%">';

	// Ligne des titres
	print '<tr class="liste_titre">';
	print_liste_field_titre(
					$langs->trans("Ref"), $_SERVER["PHP_SELF"], "p.lastname", 
					$begin, $param, '', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("Product"), $_SERVER["PHP_SELF"], "p.firstname", 
					$begin, $param, '', $sortfield, $sortorder
	);
	
	print_liste_field_titre(
					$langs->trans("FactoryDateStartPlannedShort"), $_SERVER["PHP_SELF"], "f.date_start_planned", 
					$begin, $param, 'align=center', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("QuantityPlannedShort"), $_SERVER["PHP_SELF"], "f.qty_planned", 
					$begin, $param, 'align=right', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("DurationPlannedShort"), $_SERVER["PHP_SELF"], "f.duration_planned", 
					$begin, $param, 'align=center', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("DateEndPlannedShort"), $_SERVER["PHP_SELF"], "f.date_end_planned", 
					$begin, $param, 'align=center', $sortfield, $sortorder
	);

	print_liste_field_titre(
					$langs->trans("DateStartMadeShort"), $_SERVER["PHP_SELF"], "f.date_start_made", 
					$begin, $param, 'align=right', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("QuantityMadeShort"), $_SERVER["PHP_SELF"], "f.qty_made", 
					$begin, $param, 'align=right', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("DurationMadeShort"), $_SERVER["PHP_SELF"], "f.duration_made", 
					$begin, $param, 'align=center', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("DateEndMadeShort"), $_SERVER["PHP_SELF"], "f.date_end_made", 
					$begin, $param, 'align=center', $sortfield, $sortorder
	);
	print_liste_field_titre(
					$langs->trans("Status"), $_SERVER["PHP_SELF"], "f.fk_statut", 
					$begin, $param, 'align=right', $sortfield, $sortorder
	);

	print "</tr>\n";

	// Ligne des champs de filtres
	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input class="flat" type="text" name="search_ref" size="5" value="'.$search_ref.'">';
	print '</td>';
	print '<td class="liste_titre">';
	print select_entrepot_list(GETPOST("entrepotid"), "entrepotid", 1, 1);
	print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" colspan=7 align="center"></td>';
	print '<td class="liste_titre" align="right">';
	print '<input type="image" value="button_search" class="liste_titre"';
	print ' src="'.img_picto($langs->trans("Search"), 'search.png', '', '', 1).'"';
	print ' value="'.dol_escape_htmltag($langs->trans("Search")).'"';
	print ' title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '&nbsp; ';
	print '<input type="image" value="button_removefilter" class="liste_titre"';
	print ' src="'.img_picto($langs->trans("Search"),'searchclear.png', '', '', 1).'"';
	print ' value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"';
	print ' title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print '</td>';
	print '</tr>';

	while ($i < min($num, $limit)) {
		$obj = $db->fetch_object($result);
		print "<tr >";

		// Name
		print '<td valign="middle">';
		$factory->fetch($obj->rowid);
		print $factory->getNomUrl(1);
		print '</td>';

		print '<td valign="middle">';
		$product->fetch($obj->fk_product);
		print $product->getNomUrl(1);
		print '</td>';
		
		// planned
		print '<td align="center">'.dol_print_date($db->jdate($obj->date_start_planned), "day").'</td>';
		print '<td align="right">'.$obj->qty_planned.'</td>';
		print '<td align="right">'.convertSecondToTime($obj->duration_planned, 'allhourmin').'</td>';
		print '<td align="center">'.dol_print_date($db->jdate($obj->date_end_planned), "day").'</td>';
		
		// made
		print '<td align="center">'.dol_print_date($db->jdate($obj->date_start_made), "day").'</td>';
		print '<td align="right">'.$obj->qty_made.'</td>';
		print '<td align="right">'.convertSecondToTime($obj->duration_made, 'allhourmin').'</td>';
		print '<td align="center">'.dol_print_date($db->jdate($obj->date_end_made), "day").'</td>';

		print '<td align="right">'.$factory->getLibStatut(4).'</td>';
		$i++;
	}

	print "</table>";
	print '</form>';

	if ($num > $limit)
		print_barre_liste(
						'', $page, $_SERVER["PHP_SELF"], 
						'&amp;begin='.$begin.'&amp;view='.$view.'&amp;userid='.$userid, 
						$sortfield, $sortorder, '', $num, $nbtotalofrecords, ''
		);

	$db->free($result);
} else
	dol_print_error($db);

print '<br>';

llxFooter();
$db->close();