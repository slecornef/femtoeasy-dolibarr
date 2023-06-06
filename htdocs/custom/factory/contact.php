<?php
/* Copyright (C) 2010		Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2012-2014	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2015-2020	Charlene Benke		<charlene@patas-monkey.com>
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
 *	   \file	   htdocs/factory/contact.php
 *	   \ingroup	factory
 *	   \brief	  Onglet de gestion des contacts de l'OF
 */

// Dolibarr environment
$res=0;
if (! $res && file_exists("../main.inc.php")) 
	$res=@include("../main.inc.php");		// For root directory
if (! $res && file_exists("../../main.inc.php")) 
	$res=@include("../../main.inc.php");	// For "custom" directory


dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';

$langs->load("factory@factory");
$langs->load("companies");

$id	 = GETPOST('id', 'int');
$ref	= GETPOST('ref', 'alpha');
$lineid = GETPOST('lineid', 'int');
$socid  = GETPOST('socid', 'int');
$action = GETPOST('action', 'alpha');

$mine   = GETPOST('mode')=='mine' ? 1 : 0;
//if (! $user->rights->projet->all->lire) $mine=1;	// Special for projects

$object = new Factory($db);
if ($id > 0 || ! empty($ref)) {
	$object->fetch($id, $ref);
	$object->fetch_thirdparty();
	$id=$object->id;
}
$product = new Product($db);
$entrepot = new Entrepot($db);

// Security check
$socid=0;
if ($user->socid > 0) $socid=$user->socid;
$result = restrictedArea($user, 'factory', $id);


/*
 * Actions
 */

// Add new contact
if ($action == 'addcontact' && $user->rights->factory->creer) {
	$result = 0;
	$result = $object->fetch($id);

	if ($result > 0 && $id > 0) {
		$contactid = (GETPOST('userid') ? GETPOST('userid', 'int') : GETPOST('contactid', 'int'));
		$result = $object->add_contact($contactid, $_POST["type"], $_POST["source"]);
	}

	if ($result >= 0) {
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
		exit;
	} else {
		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
			$langs->load("errors");
			setEventMessage($langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType"), 'errors');
		} else 
			setEventMessage($object->error, 'errors');
	}
}

// bascule du statut d'un contact
if ($action == 'swapstatut' && $user->rights->factory->creer) {
	if ($object->fetch($id))
		$result=$object->swapContactStatus(GETPOST('ligne', 'int'));
	else
		dol_print_error($db);
}

// Efface un contact
if (($action == 'deleteline' || $action == 'deletecontact') && $user->rights->factory->creer) {
	$object->fetch($id);
	$result = $object->delete_contact(GETPOST("lineid"));

	if ($result >= 0) {
		header("Location: contact.php?id=".$object->id);
		exit;
	} else
		dol_print_error($db);
}


/*
 * View
 */

$result = $product->fetch($object->fk_product);
$productid= $object->fk_product;

$result = $entrepot->fetch($object->fk_entrepot);
$entrepotid= $object->fk_entrepot;


$help_url="EN:Module_Factory|FR:Module_Factory|ES:M&oacute;dulo_Factory";
llxHeader('', $langs->trans("Factory"), $help_url);

$form = new Form($db);
$formcompany= new FormCompany($db);
$contactstatic=new Contact($db);
$userstatic=new User($db);


/* *************************************************************************** */
/*																			 */
/* Mode vue et edition														 */
/*																			 */
/* *************************************************************************** */

if ($id > 0 || ! empty($ref)) {
	// To verify role of users

	$head = factory_prepare_head($object);
	dol_fiche_head($head, 'contact', $langs->trans("Factory"), -1, 'factory@factory');

	if ((int) DOL_VERSION >= 6)
		$urllink='list.php';
	else
		$urllink='list-old.php';

	$linkback = '<a href="'.$urllink.'?restore_lastsearch_values=1' . (! empty($productid) ? '&productid=' . $productid : '') . '">' . $langs->trans("BackToList") . '</a>';

	// factory card
	$morehtmlref='<div class="refidno">';
	// Ref product
	$morehtmlref.='<br>'.$langs->trans('Product') . ' : ' . $product->getNomUrl(1)." - ".$product->label;
	if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
		$morehtmlref.=' (<a href="'.$urllink.'?productid='.$object->fk_product.'">'.$langs->trans("OtherFactory").'</a>)';

	// ref storage
	// rendre modifiable
	$morehtmlref.='<br>'.$langs->trans("Warehouse").' :';
		if ($object->fk_entrepot >0)
			$morehtmlref.=$entrepot->getNomUrl(1)." - ".$entrepot->lieu." (".$entrepot->zip.")" ;
	if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
		$morehtmlref.=' (<a href="'.$urllink.'?entrepotid='.$object->fk_entrepot.'">'.$langs->trans("OtherFactory").'</a>)';

	$morehtmlref.='</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	
	
	// Contacts lines (modules that overwrite templates must declare this into descriptor)
	$dirtpls=array_merge($conf->modules_parts['tpl'], array('/core/tpl'));
	foreach ($dirtpls as $reldir) {
		$res=@include dol_buildpath($reldir.'/contacts.tpl.php');
		if ($res) break;
	}

	print '</div>';
}
dol_fiche_end();

llxFooter();
$db->close();