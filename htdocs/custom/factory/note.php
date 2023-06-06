<?php
/* Copyright (C) 2014-2022		Charlene BENKE		<charlene@patas-monkey.com>
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
 *	\file	   htdocs/factory/note.php
 *	\ingroup	factory
 *	\brief	  Fiche d'information sur un OF
 */


$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

$langs->load('companies');
$langs->load("factory@factory");

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');

// Security check
if ($user->socid) $socid=$user->socid;
$result = restrictedArea($user, 'factory');

$object = new Factory($db);
$object->fetch($id, $ref);

$product = new Product($db);
$entrepot = new Entrepot($db);

$result = $product->fetch($object->fk_product);
$productid= $object->fk_product;

$result = $entrepot->fetch($object->fk_entrepot);
$entrepotid= $object->fk_entrepot;

/*
 * Actions
 */
if ($action == 'setnote_public' && $user->rights->factory->creer) {
	$result=$object->update_note_public(dol_html_entity_decode(GETPOST('note_public', "restricthtml"), ENT_QUOTES), '_public');
	if ($result < 0) 
		dol_print_error($db, $object->error);
} elseif ($action == 'setnote_private' && $user->rights->factory->creer) {
	$result=$object->update_note(dol_html_entity_decode(GETPOST('note_private', "restricthtml"), ENT_QUOTES), '_private');
	if ($result < 0) 
		dol_print_error($db, $object->error);
}


/*
 * View
 */
llxHeader();

$form = new Form($db);

dol_htmloutput_mesg($mesg);


$head=factory_prepare_head($object, $user);
dol_fiche_head($head, 'notes', $langs->trans('Factory'), -1, 'factory@factory');


if ((int) DOL_VERSION >= 6)
	$urllink='list.php';
else
	$urllink='list-old.php';

$linkback = '<a href="'.$urllink.'?restore_lastsearch_values=1' . (! empty($productid) ? '&productid=' . $productid : '') . '">' . $langs->trans("BackToList") . '</a>';


// factory card

$morehtmlref='<div class="refidno">';

// ajouter la date de création de l'OF

// Ref product
$morehtmlref.='<br>'.$langs->trans('Product') . ' : ' . $product->getNomUrl(1)." - ".$product->label;
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.$urllink.'?productid='.$object->fk_product.'">'.$langs->trans("OtherFactory").'</a>)';

// ref storage
// rendre modifiable
$morehtmlref.='<br><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("Warehouse").' :</td>';
$morehtmlref.='<td>';
if ($object->fk_entrepot >0)
	$morehtmlref.=$entrepot->getNomUrl(1)." - ".$entrepot->lieu." (".$entrepot->zip.")" ;
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.$urllink.'?entrepotid='.$object->fk_entrepot.'">'.$langs->trans("OtherFactory").'</a>)';

$morehtmlref.='</td></tr>';
$morehtmlref.='</table>';


$morehtmlref.='</div>';


dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

include(DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php');

print "</div>";
llxFooter();
$db->close();