<?php
/* 
 * Copyright (C) 2014 		Florian HENRY 		<florian.henry@open-concept.pro>
 * Copyright (C) 2014-2022	Charlene BENKE		<charlene@patas-monkey.com>
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
 * \file factory/info.php
 * \ingroup factory
 * \brief info of factory
 */
$res = @include("../main.inc.php"); // For root directory
if (! $res)
	$res = @include("../../main.inc.php"); // For "custom" directory
if (! $res)
	die("Include of main fails");

	dol_include_once('/factory/class/factory.class.php');
	dol_include_once('/factory/core/lib/factory.lib.php');
	
	require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
	require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";

require_once(DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php');

// Security check
if (! $user->rights->factory->lire)
	accessforbidden();

$id = GETPOST('id', 'int');
$ref= GETPOST('id', 'alpha');

/*
 * View
 */

llxHeader('', $langs->trans("Factory"));

$object = new Factory($db);
$product = new Product($db);
$entrepot = new Entrepot($db);


$result = $object->fetch($id, $ref);
$result = $product->fetch($object->fk_product);
$result = $entrepot->fetch($object->fk_entrepot);

$object->info($id);

$head = factory_prepare_head($object, $user);

dol_fiche_head($head, 'infos', $langs->trans("Factory"), -1, 'factory@factory');

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';

// Ref product
$urllink="list.php";
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


print '<table width="100%"><tr><td>';
dol_print_object_info($object);
print '</td></tr></table>';
print '</div>';

llxFooter();
$db->close();