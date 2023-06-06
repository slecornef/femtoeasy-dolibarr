<?php
/* Copyright (C) 2003-2007	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Marc Barilley / Ocebo	<marc@ocebo.com>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2013		Cédric Salvador			<csalvador@gpcsolutions.fr>
 * Copyright (C) 2013-2021	Charlene BENKE			<charlene@patas-monkey.com>
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
 *	\file	   htdocs/Factory/document.php
 *	\ingroup	Factory
 *	\brief	  Management page of documents attached to a Factory
 */

// Dolibarr environment
$res=0;
if (! $res && file_exists("../main.inc.php")) 
	$res=@include("../main.inc.php");		// For root directory
if (! $res && file_exists("../../main.inc.php")) 
	$res=@include("../../main.inc.php");	// For "custom" directory


dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/images.lib.php";
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . "/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';

$langs->load('factory@factory');
$langs->load('other');

$action		= GETPOST('action');
$confirm	= GETPOST('confirm');
$id			= GETPOST('id', 'int');
$ref		= GETPOST('ref');
$sortfield=GETPOST("sortfield", 'alpha');
$sortorder=GETPOST("sortorder", 'alpha');

// Security check
if ($user->socid) {
	$action='';
	$socid = $user->socid;
}

$result=restrictedArea($user, 'factory');

$object = new Factory($db);
$product = new Product($db);
$entrepot = new Entrepot($db);

/*
 * Actions
 */
if ($object->fetch($id, $ref)) {
	$result = $product->fetch($object->fk_product);
	$object->fetch_thirdparty();
	$upload_dir = $conf->factory->dir_output . "/" . dol_sanitizeFileName($object->ref);
	
}

include_once DOL_DOCUMENT_ROOT . '/core/actions_linkedfiles.inc.php';


/*
 * View
 */

llxHeader('', $langs->trans('Factory'), 'EN:Factory|FR:Factory|ES:Factory');
$form = new Form($db);

if ($id > 0 || ! empty($ref)) {
	if ($object->fetch($id, $ref)) {
		$object->fetch_thirdparty();
		
		$result = $product->fetch($object->fk_product);
		$result = $entrepot->fetch($object->fk_entrepot);


		$upload_dir = $conf->factory->dir_output.'/'.dol_sanitizeFileName($object->ref);

		$head = factory_prepare_head($object, $user);
		dol_fiche_head($head, 'document', $langs->trans("Factory"), -1, 'factory@factory');

		// Construit liste des fichiers
		$filearray=dol_dir_list(
						$upload_dir, "files", 0, '', '\.meta$', 
						$sortfield, (strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC), 1
		);
		$totalsize=0;
		foreach ($filearray as $key => $file)
			$totalsize+=$file['size'];

		$linkback = '<a href="list.php?restore_lastsearch_values=1' . (! empty($productid) ? '&productid=' . $productid : '') . '">' . $langs->trans("BackToList") . '</a>';

		// factory card
		$morehtmlref='<div class="refidno">';

		// ajouter la date de création de l'OF

		// Ref product
		$morehtmlref.='<br>'.$langs->trans('Product') . ' : ' . $product->getNomUrl(1)." - ".$product->label;
		if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
			$morehtmlref.=' (<a href="list.php?productid='.$object->fk_product.'">'.$langs->trans("OtherFactory").'</a>)';

		// ref storage
		// rendre modifiable
		$morehtmlref.='<br><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("Warehouse").' : </td>';
		$morehtmlref.='<td>';
		if ($object->fk_entrepot >0)
			$morehtmlref.=$entrepot->getNomUrl(1)." - ".$entrepot->lieu." (".$entrepot->zip.")" ;
		if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
			$morehtmlref.=' (<a href="list.php?entrepotid='.$object->fk_entrepot.'">'.$langs->trans("OtherFactory").'</a>)';
		$morehtmlref.='</td></tr>';
		$morehtmlref.='</table>';
		$morehtmlref.='</div>';

		dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';

		print "</div>\n";

		$modulepart = 'factory';
		$permission = $user->rights->factory->creer;
		$permissiontoadd = $user->rights->factory->creer;
		$param = '&id=' . $object->id;
		include_once DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_post_headers.tpl.php';
	} else
		dol_print_error($db);

}
else
	header('Location: index.php');

llxFooter();
$db->close();