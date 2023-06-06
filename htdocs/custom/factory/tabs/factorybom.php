<?php
/* Copyright (C) 2001-2007	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2011		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2015-2022	Charlene BENKE			<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *  \file	   htdocs/factory/tabs/factorybom.php
 *  \ingroup	product
 *  \brief	  Page de suivi des Ordres de fabrication depuis un BOM
 */

$res=@include("../../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/bom/lib/bom.lib.php';
require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/projet/class/project.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');


$langs->load("bills");
$langs->load("products");
$langs->load("companies");
$langs->load('mrp');
$langs->load("factory@factory");

$factoryid=GETPOST('factoryid', 'int');
$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$key=GETPOST('key');
$parent=GETPOST('parent');

// Security check
//$socid=0;
//if ($user->socid) $socid=$user->socid;
//$result=restrictedArea($user, 'bom', $id, '');

$mesg = '';

$object = new Bom($db);
$factory = new Factory($db);
$product = new Product($db);
$entrepot=new Entrepot($db);

$productid=0;
if ($id || $ref) {
	$result = $object->fetch($id, $ref);
	$productid=$object->fk_product;
	$object->fetch_thirdparty();
	$id=$object->id;

	$result = $product->fetch($object->fk_product);
	$result = $entrepot->fetch($object->fk_warehouse);
}

/*
 * Actions
 */
// build product on each store
if ($action == 'createof' ) {
	if (! empty($conf->global->FACTORY_ADDON) && is_readable(dol_buildpath("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php")))
		dol_include_once("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php");


	// on récupère les valeurs du BOM
	$factory->fk_bom=$id;
	$factory->fk_product=$object->fk_product;
	$factory->fk_entrepot=$object->fk_warehouse;
	$factory->qty_planned=$object->qty;
	$factory->description=$object->description;
	$factory->duration_planned=$object->duration;
	
	$newref=$factory->createof();
	// on boucle sur la liste des composant du BOM
	// a ajouter comme composition de l'OF
	foreach ($object->lines as $line) {
		// pmp est à à zéro
		//var_dump($line);
		$factory->add_componentOF($newref, $line->fk_product, $line->qty, 0, $line->unit_cost, $line->qty_frozen, $line->description);
	}

	// on redirige sur l'of que l'on vient de créer
	header("Location: ".dol_buildpath('/factory/fiche.php', 1).'?id='.$newref);
	exit;
}


/*
 * View
 */


$form = new Form($db);

llxHeader("", "", $langs->trans("FactoryBom"));

/*
	* Show tabs
	*/
$head = bomPrepareHead($object);

dol_fiche_head($head, 'factory', $langs->trans("BillOfMaterials"), -1, 'bom');

// Object card
// ------------------------------------------------------------
$linkback = '<a href="'.dol_buildpath('/bom/bom_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

// BOM card
$morehtmlref='<div class="refidno">';
// Ref product
$morehtmlref.='<br>'.$langs->trans('Product') . ' : ' . $product->getNomUrl(1);
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.dol_buildpath('/bom/bom_list.php', 1).'?search_fk_product='.$object->fk_product.'">'.$langs->trans("OtherFactory").'</a>)';	

$morehtmlref.='<br>'.$langs->trans('Warehouse') . ' : ' . $entrepot->getNomUrl(1);
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.dol_buildpath('/bom/bom_list.php', 1).'?search_fk_warehouse='.$object->fk_warehouse.'">'.$langs->trans("OtherFactory").'</a>)';	
$morehtmlref.='</div>';


dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
// il faut un entrepot
if ($object->fk_warehouse >0)
	$newcardbutton= dolGetButtonTitle($langs->trans('NewOFFromBOM'), '', 'fa fa-plus-circle', 'factorybom.php?action=createof&id='.$id, '', $user->rights->bom->write);
else
	$newcardbutton= dolGetButtonTitle($langs->trans('NewOFFromBOM'), $langs->trans("NeedWarehouseSelect"), 'fa fa-plus-circle', 'factorybom.php?action=createof&id='.$id, '', -1);


print_barre_liste($langs->trans("ListAssociatedOfInBOM"),'', $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bom', 0, $newcardbutton, '', $limit, 0, 0, 1);

//print load_fiche_titre('');
print '<table id="tablelines" class="noborder noshadow" width="100%">';
print '<tr class="liste_titre nodrag nodrop">';

print '<td width=150px>'.$langs->trans('Ref').'</td>';
print '<td width=150px>'.$langs->trans('RefProduct').'</td>';
print '<td width=150px>'.$langs->trans('Warehouse').'</td>';
print '<td align=center><label for="">'.$langs->trans('DateStartOF').'</label></td>';
print '<td align=center><label for="">'.$langs->trans('DateEndOF').'</label></td>';
// Duration
print '<td align="right" width="100"><label for="qty">'.$langs->trans('DurationInOFPlanned').'</label></td>';
print '<td align="right" width="100"><label for="qty">'.$langs->trans('DurationInOFMade').'</label></td>';

// Qty
print '<td align="right" width="100"><label for="qty">'.$langs->trans('QtyInOFPlanned').'</label></td>';
print '<td align="right" width="100"><label for="qty">'.$langs->trans('QtyInOFMade').'</label></td>';
print '<td align="right" width="100"><label for="qty">'.$langs->trans('Statut').'</label></td>';

print "</tr>\n";

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."factory as f";
$sql .= " WHERE fk_bom= ".$id;

$resOFLink = $db->query($sql);
$num = $db->num_rows($resOFLink);
$i=0;
$var=true;

if ($num == 0) 
	print '<tr><td colspan="4">'.$langs->trans("NoLinkedOFMatchFound").'</td></tr>';

while ($i < $num) {
	$objp = $db->fetch_object($resOFLink);
	$factory->fetch($objp->rowid);
	$product->fetch($objp->fk_product);
	$entrepot->fetch($objp->fk_entrepot);
	
	$var=!$var;
	$i++;
	print "\n<tr ".$bc[$var].">";
	print "<td>".$factory->getNomURL(1)."</td>";
	print "<td>".$product->getNomURL(1)."</td>";
	print "<td>".$entrepot->getNomURL(1)."</td>";
	if ($factory->statut >= 2) {
		print "<td align=center>".dol_print_date($factory->date_start_made, 'daytext')."</td>";
		print "<td align=center>".dol_print_date($factory->date_end_made, 'daytext')."</td>";
	} else {
		print "<td align=center>".dol_print_date($factory->date_start_planned, 'daytext')."</td>";
		print "<td align=center>".dol_print_date($factory->date_end_planned, 'daytext')."</td>";
	}
	
	print "<td align=right>".($factory->duration_planned?convertSecondToTime($factory->duration_planned, 'allhourmin'):"")."</td>";
	print "<td align=right>".($factory->duration_made?convertSecondToTime($factory->duration_made, 'allhourmin'):"")."</td>";
	
	print "<td align=right>".$factory->qty_planned."</td>";
	print "<td align=right>".$factory->qty_made."</td>";
	print "<td align=right>".$factory->getLibStatut(4)."</td>";
	print '</tr>';
}
print '</table>';



dol_htmloutput_mesg($mesg);

/* Barre d'action				*/
print '<div class="tabsAction">';
print '</div>';
llxFooter();
$db->close();