<?php
/* Copyright (C) 2001-2007		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011		Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005			Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012		Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006			Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2011			Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2013-2022		Charlene BENKE			<charlene@patas-monkey.com>
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
 *  \file	   htdocs/factory/fiche.php
 *  \ingroup	factory
 *  \brief	  Page des Ordres de fabrication sur la fiche produit
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT."/core/lib/product.lib.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";

require_once DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/treeview.lib.php';

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

if (! empty($conf->global->FACTORY_ADDON) 
	&& is_readable(dol_buildpath("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php")))
	dol_include_once("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php");


$langs->load("bills");
$langs->load("products");
$langs->load("stocks");
$langs->load("factory@factory");

$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$lineid=GETPOST('lineid', 'int');

$fk_product=GETPOST('fk_product','int');
$qty_planned=GETPOST('qty_planned','int');
$fk_entrepot=GETPOST('fk_entrepot', 'int');

$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$keysearch=GETPOST('keysearch', 'alpha');
$parent=GETPOST('parent','int');

// Security check
//if (! empty($user->socid)) $socid=$user->socid;
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
$result = restrictedArea($user, 'factory');

$mesg = '';

$product = new Product($db);
$object = new Factory($db);
$entrepot = new Entrepot($db);

$extrafields = new ExtraFields($db);

$formProduct_static = new FormProduct($db);
$form = new Form($db);
$formfile = new FormFile($db);


if ($action=="create"){
	// on vérifie qu'un produit ou un BOM a été sélectionné
	if (GETPOST("productid", 'int') > 0 || GETPOST("bomid",'int') > 0) {
		// on redirige vers la creation sur la fiche produit
		$szurl="product/fiche.php?action=createof&verifyof=1";
		$szurl.="&id=".GETPOST("productid", 'int');
		$szurl.="&bomid=".GETPOST("bomid", 'int');
		$szurl.="&entrepotid=".GETPOST("entrepotid");
		$szurl.="&nbToBuild=".GETPOST("nbToBuild");
		$szurl.="&plannedstarthour=".GETPOST("plannedstarthour");
		$szurl.="&plannedstartmin=".GETPOST("plannedstartmin");
		$szurl.="&plannedstartday=".GETPOST("plannedstartday");
		$szurl.="&plannedstartmonth=".GETPOST("plannedstartmonth");
		$szurl.="&plannedstartyear=".GETPOST("plannedstartyear");
		$szurl.="&plannedendhour=".GETPOST("plannedendhour");
		$szurl.="&plannedendmin=".GETPOST("plannedendmin");
		$szurl.="&plannedendday=".GETPOST("plannedendday");
		$szurl.="&plannedendmonth=".GETPOST("plannedendmonth");
		$szurl.="&plannedendyear=".GETPOST("plannedendyear");
		$szurl.="&workloadhour=".GETPOST("workloadhour");
		$szurl.="&workloadmin=".GETPOST("workloadmin");

		header("Location: ".$szurl);

		exit;
	} else	{
		$mesg = '<div class="error">'.$langs->trans("NeedBOMorProductSelected").'</div>';
		$action="add";
	}
}

if ($action=="updateline") {
	$factoryDet_static = new Factorydet($db);
	
	$factoryDet_static->rowid = GETPOST('lineid', 'int');
	$factoryDet_static->fk_product = GETPOST('fk_product','int');
	$factoryDet_static->qtyplanned = GETPOST('qtyplanned','int');
	$factoryDet_static->globalqty = !empty(GETPOST('globalqty', 'int'))?1:0;
	$factoryDet_static->pmp = GETPOST('pmp', 'int');
	$factoryDet_static->price = GETPOST('price', 'int');
	$factoryDet_static->ordercomponent = !empty(GETPOST('ordercomponent','none'))?GETPOST('ordercomponent','int'):0;
	if ($factoryDet_static->update($user))
		$mesg = '<div class="info">'.$langs->trans("LineChanged").'</div>';
	else
		$mesg = '<div class="error">'.$langs->trans("ErrorOnUpdateLine").'</div>';
	$action="";
}


$productid=0;
if ($id || $ref) {
	// l'of, l'entrepot et le produit associé
	$result = $object->fetch($id, $ref);
	if (!$id) $id = $object->id;

	$result = $product->fetch($object->fk_product);
	$productid= $object->fk_product;
	
	$result = $entrepot->fetch($object->fk_entrepot);
	$entrepotid= $object->fk_entrepot;
		
	//var_dump($object);
}

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('factorycard', 'globalcard'));

$parameters = array('product' => $product);
// Note that $action and $object may have been modified by some hooks
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); 
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

/*
 * Actions
 */

if (empty($reshook)) {
	// mise à jour des composants
	if ($action == 'add_prod' 
		&& $cancel <> $langs->trans("Cancel") 
		&& $object->statut == 0		// seulement si le module est à l'état brouillon
		&& $user->rights->factory->creer ) {
		$error=0;
		for ($i=0;$i < $_POST["max_prod"];$i++) {
			//print "<br> ".$i.": ".$_POST["prod_id_chk".$i];
			// suppression de l'extrafields précédent si existant
			if (!empty($_POST["extrafieldsid_".$i])) {
				$sql = 'DELETE from '.MAIN_DB_PREFIX.'factorydet_extrafields';
				$sql .= ' WHERE fk_object = '.$_POST["extrafieldsid_".$i];
				$db->query($sql);
			}

			if (!empty($_POST["prod_id_chk".$i])) {
				$newComponentId = $object->add_componentOF(
								$id, $_POST["prod_id_".$i], 
								$_POST["prod_qty_".$i], 0, 0, 
								$_POST["prod_id_globalchk".$i], 
								$_POST["descComposant".$i], $_POST["prod_order_".$i]
				);

				if ($newComponentId >0)  {
					$ExtrafieldTable="factorydet";
					// on met à jour l'extrafields
					$extrafields->fetch_name_optionals_label($ExtrafieldTable);
					// on crée cet objet juste pour accéder à la fonction native de création d'extrafields 
					
					//$extrafieldsElement->fetch_optionals();
					if (!empty($extrafields->attributes[$ExtrafieldTable]['label']) 
						&& is_array($extrafields->attributes[$ExtrafieldTable]['label']) 
						&& count($extrafields->attributes[$ExtrafieldTable]['label']) > 0) {
						$extrafieldsElement = New Factorydet($db);
						// on aliment les valeurs de l'extrafields
						foreach ($extrafields->attributes[$ExtrafieldTable]['label'] as $key => $val) {
							$extrafieldsElement->array_options["options_".$key]=$_POST["options_".$key."_".$i];
						}
						// on ajoute l'id pour faire le lien avant ajout des valeurs dans l'extrafields
						$extrafieldsElement->id = $newComponentId;
						$extrafieldsElement->insertExtraFields();
					}
	
					$action = 'edit';
				} else {
					$error++;
					$action = 're-edit';
					if ($object->error == "isFatherOfThis") 
						$mesg = '<div class="error">'.$langs->trans("ErrorAssociationIsFatherOfThis").'</div>';
					else 
						$mesg=$object->error;
				}
			} else {
				if ($object->del_componentOF($id, $_POST["prod_id_".$i]) > 0)
					$action = 'edit';
				else {
					$error++;
					$action = 're-edit';
					$mesg=$product->error;
				}
			}
		}
		if (! $error) {
			$action = '';
			Header("Location: fiche.php?id=".$_POST["id"]);
			exit;
		}

	} elseif ($action == 'confirm_clone' && $confirm == 'yes' && $user->rights->factory->creer) {
		// Action clone object
		if (1 == 0 && ! GETPOST('clone_content') && ! GETPOST('clone_receivers'))
			setEventMessage($langs->trans("NoCloneOptionsSpecified"), 'errors');
		else {
			if ($object->id > 0) {
				// Because createFromClone modifies the object, we must clone it so that we can restore it later
				$orig = dol_clone($object);

				$result=$object->createFromClone($fk_product, $qty_planned, $fk_entrepot);
				if ($result > 0) {
					header("Location: ".$_SERVER['PHP_SELF'].'?id='.$result);
					exit;
				} else {
					setEventMessage($object->error, 'errors');
					$mesg= '<div class="error">'.$object->error.'</div>';
					$object = $orig;
					$action='';
				}
			}
		}
	} elseif ($cancel == $langs->trans("Cancel")) {
		$action = '';
		Header("Location: fiche.php?id=".$_POST["id"]);
		exit;
	} elseif (substr($action, 0, 7) == 'setExFi' && $user->rights->factory->creer) {
		$keyExFi= substr($action, 7);
		$res=$object->fetch_optionals($object->id, $extralabels);
		$object->array_options["options_".$keyExFi]=$_POST["options_".$keyExFi];
		$object->insertExtraFields();
		$action = "";
	} elseif ($action == 'seteditdatestartmade') {
		$datestartmade=dol_mktime(
						'23', '59', '59',
						$_POST["datestartmademonth"],
						$_POST["datestartmadeday"],
						$_POST["datestartmadeyear"]
		);
		//$object->fetch($id);
		$result=$object->set_datestartmade($user, $datestartmade);
		if ($result < 0) 
			dol_print_error($db, $object->error);
		$action = "";
	} elseif ($action == 'seteditdatestartplanned') {
		$datestartplanned=dol_mktime(
						'23', '59', '59',
						$_POST["datestartplannedmonth"],
						$_POST["datestartplannedday"],
						$_POST["datestartplannedyear"]
		);
		
		//$object->fetch($id);
		$result=$object->set_datestartplanned($user, $datestartplanned);
		if ($result < 0)
			dol_print_error($db, $object->error);
		$action = "";

	} elseif ($action == 'seteditdateendplanned') {
		$dateendplanned=dol_mktime(
						'23', '59', '59',
						$_POST["dateendplannedmonth"],
						$_POST["dateendplannedday"],
						$_POST["dateendplannedyear"]
		);

		//$object->fetch($id);
		$result=$object->set_dateendplanned($user, $dateendplanned);
		if ($result < 0) 
			dol_print_error($db, $object->error);
		$action = "";

	} elseif ($action == 'seteditdurationplanned') {
		$dateendplanned = GETPOST("duration_plannedhour")*3600+GETPOST("duration_plannedmin")*60;;

		//$object->fetch($id);
		$result=$object->set_durationplanned($user, $dateendplanned);
		if ($result < 0) 
			dol_print_error($db, $object->error);
		$action = "";

	} elseif ($action == 'setdescription') {
		//$object->fetch($id);
		$result=$object->set_description($user, $_POST["description"]);
		if ($result < 0) 
			dol_print_error($db, $object->error);
		$action = "";

	} elseif ($action == 'setentrepot') {
		//$object->fetch($id);
		$result=$object->set_entrepot($user, GETPOST("fk_entrepot"));
		if ($result < 0) 
			dol_print_error($db, $object->error);
		$action = "";

	} elseif ($action == 'setquantity') {
		//$object->fetch($id);
		$result=$object->set_qtyplanned($user, GETPOST("qty_planned"));
		if ($result < 0) dol_print_error($db, $object->error);
		$action = "";
	} elseif ($action == 'builddoc') {
		/*
		 * Generate order document
		 * define into /core/modules/factory/modules_factory.php
		 */
	
		// Save last template used to generate document
		if (GETPOST('model')) $object->setDocModel($user, GETPOST('model', 'alpha'));
	
		// Define output language
		$outputlangs = $langs;
		$newlang='';
		if (!empty($conf->global->MAIN_MULTILANGS) && empty($newlang)  && ! empty($_REQUEST['lang_id'])) 
			$newlang=$_REQUEST['lang_id'];
		if (!empty($conf->global->MAIN_MULTILANGS) && empty($newlang)) 
			$newlang=$object->client->default_lang;
		if (! empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
		}
		$result=factory_create($db, $object, $object->model_pdf, $outputlangs); //, $hidedetails, $hidedesc, $hideref);
	
		if ($result <= 0) {
			dol_print_error($db, $result);
			exit;
		}
		$action = "";
	} elseif ($action == 'cancelof') {
		$object->statut = 9;
		$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
		$sql.= " SET fk_statut =9";
		$sql.= " WHERE rowid = ".$id;
		if ($db->query($sql)) {
			// Call trigger
			$result=$object->call_trigger('FACTORY_CANCEL', $user);
			if ($result < 0) $error++;
			// on supprime les mouvements de stock ??
		}
		$action="";
	} elseif ($action == 'remove_file') {
		// Remove file in doc form
			$langs->load("other");
		$upload_dir = $conf->factory->dir_output;
		$file = $upload_dir . '/' . GETPOST('file');
		$ret = dol_delete_file($file, 0, 0, 0, $object);
		if ($ret) setEventMessage($langs->trans("FileWasRemoved", GETPOST('urlfile')));
		else setEventMessage($langs->trans("ErrorFailToDeleteFile", GETPOST('urlfile')), 'errors');
		$action="";

	} elseif ($action == 'clone') {

		// pour l'affichage soit de la liste des produits, soit la recherche produit
		if (! empty($conf->use_javascript_ajax) && ! empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT)) {
			$placeholder='';
			$selected_input_value=$product->ref;

			// mode=1 means customers products
			$urloption='htmlname='.'fk_product'.'&outjson=1&price_level=0&type=&mode=1&status=1&finished=2';
			if (! empty($conf->global->MAIN_HTML5_PLACEHOLDER)) $placeholder=' placeholder="'.$langs->trans("RefOrLabel").'"';
			if (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && !empty($socid)) $urloption.='&socid='.$socid;
			$productselectlist= ajax_autocompleter(
							$object->fk_product, 'fk_product', DOL_URL_ROOT.'/product/ajax/products.php', 
							$urloption, $conf->global->PRODUIT_USE_SEARCH_TO_SELECT, 0
			);
			$productselectlist.= '<input type="text" size="20" name="search_fk_product" id="search_fk_product"';
			$productselectlist.= ' value="'.$selected_input_value.'"'.$placeholder.' />';
		} else
			$productselectlist= $form->select_produits_list($object->fk_product, 'fk_product', '');

		// Create an array for form
		$formquestion = array(
			// 'text' => $langs->trans("ConfirmClone"),
			// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' =>
			// 1),
			array (	'type' => 'text',
					'size' => 3,
					'name' => 'qty_planned',
					'label' => $langs->trans("QtyPlannedToBuild"),
					'value' => $object->qty_planned
				),
			array (	'type' => 'other',
					'name' => 'fk_product',
					'label' => $langs->trans("SelectProduct"),
					'value' => $productselectlist
				),
			array (	'type' => 'other',
					'name' => 'fk_entrepot',
					'label' => $langs->trans("SelectWarehouse"),
					'value' =>
					$formProduct_static->selectWarehouses($object->fk_entrepot,"fk_entrepot", 'warehouseopen')
				)
			);

		// Confirmation du clonage de l'OF
		$formconfirm= $form->formconfirm(
						$_SERVER["PHP_SELF"] . '?id=' . $object->id, 
						$langs->trans('CloneOF'), 
						$langs->trans('ConfirmCloneOF'). $object->ref,
						'confirm_clone', $formquestion, 'yes', 1, 300, 650
		);
		// print $formconfirm;
	} elseif ($action == 'updateprice') {
		// on modifie les prix 
		//		$prodsfather = $object->getFather($object->id); //Parent Products
		//		$object->get_sousproduits_arbo();
		//		// Number of subproducts
		//		$prods_arbo = $object->get_arbo_each_prod();
		//		// something wrong in recurs, change id of object
		//		$object->id = $id;
		$prods_arbo= $object->getChildsOF($object->id);

		// List of subproducts
		if (count($prods_arbo) > 0) {
			foreach ($prods_arbo as $value)
				$object->updateOFprices(
								$value['id'], GETPOST("prod_pmp_".$value['id']), 
								GETPOST("prod_price_".$value['id'])
				);
		}
		$action="";
	} elseif ($action == 'getdefaultprice') {	
		$object->getdefaultprice(1);  // mode factorydet
		$action="";
	} elseif ($action == 'dellink') {	
		$permissiondellink = $user->rights->factory->creer; // Used by the include of actions_dellink.inc.php
		$object =  $object;
		include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php'; // Must be include, not include_once
		$object =  null;
		$action="";
	} elseif ($action == "productsupplierok") {
		// on valide la quantité produit chez le sous-traitant
		$action="";
	}
	


	// Clone confirmation
	//if (! $formconfirm) {
	//	$parameters = array('lineid' => $lineid);
		// Note that $action and $object may have been modified by hook
	//	$formconfirm = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); 
	//}
}

/*
 * View
 */

$titre=$langs->trans("Factory");

$arrayofjs = array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.js', '/includes/jquery/plugins/jquerytreeview/lib/jquery.cookie.js');
$arrayofcss = array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.css');

llxHeader('', $titre, '', '', 0, 0, $arrayofjs, $arrayofcss);

if ($action == 'clone') {
	print $formconfirm;
}
 
if ($id || $ref) {
 
	$extralabels=$extrafields->fetch_name_optionals_label('factory');

	$res=$object->fetch_optionals($object->id, $extralabels);

	
	$head=factory_prepare_head($object, $user);
	$picto="factory@factory";

	dol_fiche_head($head, 'factoryorder', $titre, -1, $picto);

	$urllink='list.php';
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
	$morehtmlref.='<br><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("Warehouse").'</td>';
	if ($action != 'editstock' && $object->statut == 0) { 
		$morehtmlref.='<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editstock&amp;id='.$object->id.'">';
		$morehtmlref.=img_edit($langs->trans('Modify'), 1).'</a> : </td>';
	}
	$morehtmlref.='<td>';
	if ($action == 'editstock') {
		$morehtmlref.='<form name="editstock" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		$morehtmlref.='<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		$morehtmlref.='<input type="hidden" name="action" value="setentrepot">';
		$morehtmlref.= $formProduct_static->selectWarehouses($object->fk_entrepot, "fk_entrepot");

		$morehtmlref.='<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		$morehtmlref.='</form>';
	} else {
		if ($object->fk_entrepot >0)
			$morehtmlref.=$entrepot->getNomUrl(1)." - ".$entrepot->lieu." (".$entrepot->zip.")" ;
	}
	if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
		$morehtmlref.=' (<a href="'.$urllink.'?entrepotid='.$object->fk_entrepot.'">'.$langs->trans("OtherFactory").'</a>)';

	$morehtmlref.='</td></tr>';
	$morehtmlref.='</table>';
	
	
	$morehtmlref.='</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border tableforfield" width="100%">';


	print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
	print $langs->trans("OFAdditionalInfos");
	print '</td></tr>';
	// pour gerer le bom que depuis la v11 (stable)
	if ( !empty($conf->global->MAIN_MODULE_BOM)) {
		require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
		$bom_static = new BOM($db);
		$bom_static->fetch($object->fk_bom);
		print '<tr><td valign=top  >'.$langs->trans("BOMLink").'</td ><td colspan="3" valign=top>';
		if ($object->fk_bom >0)
			print $bom_static->getNomUrl(1);
		print '</td></tr>';	
	}


	// Date start planned
	print '<tr><td valign=top width=40% ><table class="nobordernopadding" width="100%"><tr>';
	print '<td align=left >'.$langs->trans("FactoryDateStartPlanned");
	if ($action != 'editdatestartplanned' && $object->statut < 2) {
		print '<td valign=top align="right">';
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=editdatestartplanned&amp;id='.$object->id.'">';
		print img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	print '</tr></table></td ><td colspan="3" valign=top>';
	if ($action == 'editdatestartplanned') {
		print '<form name="editdatestartplanned" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="seteditdatestartplanned">';
		print $form->selectDate($object->date_start_planned, 'datestartplanned', 0, 0, '', "datestartplanned");
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	} else
		print dol_print_date($object->date_start_planned, 'day');
	print '</td></tr>';


	// Date start made
	print '<tr><td valign=top  ><table class="nobordernopadding" width="100%"><tr>';
	print '<td align=left><b>'.$langs->trans("DateStartMade").'</b><br></td>';

	// c'est la saisie de cette date qui conditionne la validation ou pas de l'OF
	if ($action != 'editdatestartmade' && $object->statut < 2) {
		print '<td valign=top align="right">';
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=editdatestartmade&amp;id='.$object->id.'">';
		print img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	print '</tr></table></td ><td colspan="3" valign=top>';
	if ($action == 'editdatestartmade') {
		print '<form name="editdatestartmade" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="seteditdatestartmade">';
		print $form->selectDate($object->date_start_made, 'datestartmade', 0, 0, '', "datestartmade");
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
		print dol_print_date($object->date_start_made, 'day');
	// pour gérer la mise en forme
	if ($object->date_start_made)	
		print '<br>';
	else
		print "<b><font color=red>".$langs->trans("DateStartMadeInfo")."</font></b>";
	print '</td></tr>';


	// Date end planned
	print '<tr><td><table class="nobordernopadding" width="100%">';
	print '<tr><td>'.$langs->trans("FactoryDateEndPlanned").'</td>';
	if ($action != 'editdateendplanned' && $object->statut == 0) {
		print '<td align="right">';
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=editdateendplanned&amp;id='.$object->id.'">';
		print img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	print '</tr></table></td><td colspan="3">';
	if ($action == 'editdateendplanned') {
		print '<form name="editdateendplanned" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="seteditdateendplanned">';
		print $form->selectDate($object->date_end_planned, 'dateendplanned', 0, 0, '', "dateendplanned");
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	} else
		print dol_print_date($object->date_end_planned, 'day');

	print '</td></tr>';

	print '<tr><td><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("QuantityPlanned").'</td>';
	if ($action != 'editquantity' && $object->statut == 0) {
		print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editquantity&amp;id='.$object->id.'">';
		print img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	print '</tr></table></td><td colspan="3">';
	if ($action == 'editquantity') {
		print '<form name="editquantity" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setquantity">';
		print '<input type="text" name="qty_planned" value="'.$object->qty_planned.'">';
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	} else
		print $object->qty_planned;


	// Planned workload
	print '<tr><td><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("FactoryDurationPlanned").'</td>';
	if ($action != 'editdurationplanned' && $object->statut == 0) { 
		print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdurationplanned&amp;id='.$object->id.'">';
		print img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	print '</tr></table></td><td colspan="3">';
	if ($action == 'editdurationplanned') {
		print '<form name="editdurationplanned" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="seteditdurationplanned">';
		print $form->select_duration('duration_planned', $object->duration_planned, 0, 'text');
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
		print convertSecondToTime($object->duration_planned, 'allhourmin');
	print '</td></tr>';

	// Other attributes
	$parameters = array( 'colspan' => ' colspan="3"');
	// Note that $action and $object may have been modified by
	$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); 
	// hook
	if (empty($reshook) && ! empty($extrafields->attributes[$object->table_element]['label'])) {
		foreach ($extrafields->attributes[$object->table_element]['label'] as $key=>$label) {
			$value=(!empty(GETPOST("options_".$key))?GETPOST("options_".$key):$object->array_options["options_".$key]);
			
			print '<tr><td><table class="nobordernopadding" width="100%"><tr><td>'.$label.'</td>';
			if ($action != 'ExFi'.$key && $object->statut == 0) { 
				print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=ExFi'.$key.'&amp;id='.$object->id.'">';
				print img_edit($langs->trans('Modify'), 1).'</a></td>';
			}
			print '</tr></table></td><td colspan="3">';
			if ($action == 'ExFi'.$key) {
				print '<form name="ExFi'.$key.'" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
				print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
				print '<input type="hidden" name="action" value="setExFi'.$key.'">';
				print $extrafields->showInputField($key, $value, '', '', '', '', 0, $object->table_element);
				print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
				print '</form>';
			} else
				print $extrafields->showOutputField($key, $value, "", $object->table_element);

			print '</td></tr>'."\n";
		}
	}


	// Description
	print '<tr><td valign=top >';
	print '<table class="nobordernopadding" width="100%"><tr>';
	print '<td valign=top >'.$langs->trans("Description").'</td>';
	if ($action != 'editdescription' && ($object->statut == 0 || $user->rights->factory->update)) { 
		print '<td align="right" ><a href="'.$_SERVER["PHP_SELF"].'?action=editdescription&amp;id='.$object->id.'">';
		print img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	print '</tr></table></td><td >';
	if ($action == 'editdescription') {
		print '<form name="editdescription" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setdescription">';
		require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
		$doleditor=new DolEditor("description", $object->description, '', '100', 'dolibarr_notes', 'In', 0, true, true, '20', '100');
		print $doleditor->Create(1);

	//	print '<textarea name="description" wrap="soft" cols="120" rows="'.ROWS_4.'">'.$object->description.'</textarea>';
		print '<input type="submit" class="butAction" value="'.$langs->trans('Modify').'">';
		print '</form>';
	} else
		print str_replace(array("\r\n", "\n"), "<br>", $object->description);
	print '</td></tr>';
	

	print '</table>';
	print '</div>';
	print '<div class="fichehalfright"><div class="ficheaddleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border tableforfield" width="100%">';

	print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
	print $langs->trans("ProductsAdditionalInfos");
	print '</th></tr>';

	print '<tr><td width=40% >'.$langs->trans("VATRate").'</td>';
	print '<td>'.vatrate($product->tva_tx.($product->tva_npr?'*':''), true).'</td></tr>';

	// Price
	print '<tr><td>'.$langs->trans("SellingPrice").'</td><td>';
	if ($product->price_base_type == 'TTC') {
		print price($product->price_ttc).' '.$langs->trans($product->price_base_type);
		$sale="";
	} else {
		print price($product->price).' '.$langs->trans($product->price_base_type);
		$sale=$product->price;
	}
	print '</td></tr>';

	// Price minimum
	print '<tr><td>'.$langs->trans("MinPrice").'</td><td>';
	if ($product->price_base_type == 'TTC')
		print price($product->price_min_ttc).' '.$langs->trans($product->price_base_type);
	else
		print price($product->price_min).' '.$langs->trans($product->price_base_type);
	print '</td></tr>';

	
	// Status (to sell)
	print '<tr><td>'.$langs->trans("Status").'</td><td colspan="2">';
	print $product->getLibStatut(2, 0);
	print '  ';
	print $product->getLibStatut(2, 1);
	print '</td></tr>';
	
	// stock physique
	print '<tr><td>'.$langs->trans("PhysicalStock").'</td>';
	$product->load_stock();
	print '<td>'.$product->stock_reel.'</td></tr>';
	
	print '</table>';
	print '</div>';

	print '</div></div>';
	print '<div style="clear:both"></div>';


	// indique si on a déjà une composition de présente ou pas
	$compositionpresente=0;

	$sortfield=GETPOST("sortfield");
	$sortorder=GETPOST("sortorder");

	$prods_arbo =$object->getChildsOF($id, $sortfield, $sortorder); 

	// on travaille avec les valeurs conservées
	if (false) {	
		$object->id =$product->id;
		$object->get_sousproduits_arbo();
		// Number of subproducts
		$prods_arbo = $object->get_arbo_each_prod();
		// somthing wrong in recurs, change id of object
		$object->id = $product->id;
	}

	print load_fiche_titre($langs->trans("FactorisedProductsNumber").' : '.count($prods_arbo), '', '');

	// définition des champs à afficher
	// fetch optionals attributes and labels
	$ExtrafieldTable="factorydet";
	$extrafields->fetch_name_optionals_label($ExtrafieldTable);
	$search_array_options = $extrafields->getOptionalsFromPost($ExtrafieldTable, '', 'search_');

	$arrayfields = array(
		'ref'=>array('label'=>$langs->trans("Ref"), 'checked'=>1),
		'label'=>array('label'=>$langs->trans("ProductLabel"), 'checked'=>1),
		'nb'=>array('label'=>$langs->trans("QtyNeedOF"), 'checked'=>1),
		'realstock'=>array('label'=>$langs->trans("RealStock"), 'checked'=>1),
		'warehousestock'=>array('label'=>$langs->trans("WarehouseStock"), 'checked'=>1),
		'qtyorder'=>array('label'=>$langs->trans("QtyOrder"), 'checked'=>0),
		'qtyOk'=>array('label'=>$langs->trans("QtyAlert"), 'checked'=>0)

	);
	if ($user->rights->factory->showprice) {
		$arraymoneyfields = array(
			'unitpmp'=>array('label'=>$langs->trans("UnitPmp"), 'checked'=>1),
			'costpmpht'=>array('label'=>$langs->trans("CostPmpHT"), 'checked'=>0),
			'unitha'=>array('label'=>$langs->trans("UnitHa"), 'checked'=>0),
			'costhaht'=>array('label'=>$langs->trans("CostHaHT"), 'checked'=>0),
			'unitpriceht'=>array('label'=>$langs->trans("FactoryUnitPriceHT"), 'checked'=>1),
			'sellingpriceht'=>array('label'=>$langs->trans("FactorySellingPriceHT"), 'checked'=>1),
			'profitamount'=>array('label'=>$langs->trans("ProfitAmount"), 'checked'=>0)
		);
		$arrayfields =  array_merge($arrayfields, $arraymoneyfields );
	}
	$arrayendfields= array(
		'weight'=>array('label'=>$langs->trans("Weight"), 'checked'=>1),
		'ordercomponent'=>array('label'=>$langs->trans("Position"), 'checked'=>0),
	);
	$arrayfields =  array_merge($arrayfields, $arrayendfields );

	if ( !empty($extrafields->attributes[$ExtrafieldTable]['label']) 
		&& is_array($extrafields->attributes[$ExtrafieldTable]['label']) 
		&& count($extrafields->attributes[$ExtrafieldTable]['label']) > 0) {
		foreach ($extrafields->attributes[$ExtrafieldTable]['label'] as $key => $val) {
			if (!empty($extrafields->attributes[$ExtrafieldTable]['list'][$key]))
				$arrayfields["ef.".$key] = array(
						'label'=>$extrafields->attributes[$ExtrafieldTable]['label'][$key], 
						'checked'=>(($extrafields->attributes[$ExtrafieldTable]['list'][$key] < 0) ? 0 : 1), 
						'position'=>$extrafields->attributes[$ExtrafieldTable]['pos'][$key], 
						'enabled'=>(abs($extrafields->attributes[$ExtrafieldTable]['list'][$key]) != 3 && $extrafields->attributes[$ExtrafieldTable]['perms'][$key])
					);
		}
	}

	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
	if ($action=='list')
		$action="";
	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields


	// List of subproducts
	if (count($prods_arbo) > 0) {
		$compositionpresente=1;
		print '<form method="POST" name="listcomponent" action="'.$_SERVER['PHP_SELF'].'">';
		//if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
		if ($action=="editline") {
			print '<input type="hidden" name="action" value="updateline">';
			print '<input type="hidden" name="lineid" value="'.$lineid.'">';
		} else
			print '<input type="hidden" name="action" value="list">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		//print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

		print '<div class="div-table-responsive">';
		print '<table class="tagtable liste">'."\n";
		$nbcol=1;
		$param="&id=".$id;
		print '<tr class="liste_titre">';
		if ($object->fk_statut ==0) 
			print '<td width=16px></td>';
		if (!empty($arrayfields['ref']['checked'])) {
			print_liste_field_titre($arrayfields['ref']['label'], $_SERVER["PHP_SELF"], 'ref', '', $param, '', $sortfield, $sortorder);
			$nbcol++;
		}
		if (!empty($arrayfields['label']['checked'])) {
			print_liste_field_titre($arrayfields['label']['label'], $_SERVER["PHP_SELF"], 'label', '', $param, '', $sortfield, $sortorder);
			$nbcol++;
		}

		if (!empty($arrayfields['nb']['checked'])) {
			print_liste_field_titre($arrayfields['nb']['label'], $_SERVER["PHP_SELF"], 'nb', '', $param, 'align=right', $sortfield, $sortorder);
			$nbcol++;
		}
		if (!empty($arrayfields['realstock']['checked'])) {
			print_liste_field_titre($arrayfields['realstock']['label'], $_SERVER["PHP_SELF"], 'realstock', '', $param, 'align=right', $sortfield, $sortorder);
			$nbcol++;
		}
		if (!empty($arrayfields['warehousestock']['checked'])) { // champs non triable	
			print_liste_field_titre($arrayfields['warehousestock']['label'], $_SERVER["PHP_SELF"], '', '', $param, 'align=right', $sortfield, $sortorder);
			$nbcol++;
		}

		if (!empty($arrayfields['qtyorder']['checked'])) {
			print_liste_field_titre($arrayfields['qtyorder']['label'], $_SERVER["PHP_SELF"], 'qtyorder', '', $param, 'align=right', $sortfield, $sortorder);
			$nbcol++;
		}
		if (!empty($arrayfields['qtyOk']['checked'])) {
			print_liste_field_titre($arrayfields['qtyOk']['label'], $_SERVER["PHP_SELF"], 'qtyOk', '', $param, 'align=right', $sortfield, $sortorder);
			$nbcol++;
		}
		if (!empty($arrayfields['unitpmp']['checked'])) {
			print_liste_field_titre($arrayfields['unitpmp']['label'], $_SERVER["PHP_SELF"], 'unitpmp', '', $param, 'align=right', $sortfield, $sortorder);
			$nbcol++;
		}
		if (!empty($arrayfields['costpmpht']['checked'])) {
			print_liste_field_titre($arrayfields['costpmpht']['label'], $_SERVER["PHP_SELF"], 'costpmpht', '', $param, 'align=right', $sortfield, $sortorder);
		}

		if (!empty($arrayfields['unitpriceht']['checked'])) {
			print_liste_field_titre($arrayfields['unitpriceht']['label'], $_SERVER["PHP_SELF"], 'unitpriceht', '', $param, 'align=right', $sortfield, $sortorder);
		}
		if (!empty($arrayfields['sellingpriceht']['checked'])) {
			print_liste_field_titre($arrayfields['costpmpht']['label'], $_SERVER["PHP_SELF"], 'sellingpriceht', '', $param, 'align=right', $sortfield, $sortorder);
		}

		if (!empty($arrayfields['profitamount']['checked'])) {
			print_liste_field_titre($arrayfields['profitamount']['label'], $_SERVER["PHP_SELF"], 'profitamount', '', $param, 'align=right', $sortfield, $sortorder);
		}

		if (!empty($arrayfields['weight']['checked'])) {
			print_liste_field_titre($arrayfields['weight']['label'], $_SERVER["PHP_SELF"], 'weight', '', $param, 'align=right', $sortfield, $sortorder);
		}
		if (!empty($arrayfields['ordercomponent']['checked'])) {
			print_liste_field_titre($arrayfields['ordercomponent']['label'], $_SERVER["PHP_SELF"], 'ordercomponent', '', $param, 'align=right', $sortfield, $sortorder);
		}


		// Extra fields
		$extrafieldsobjectkey=$ExtrafieldTable;
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';

		print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
		print "</tr>\n";

		print '</tr>';
		$mntTot=0;
		$pmpTot=0;
		$TotWeight=0;
		$btopChild = false;
		foreach ($prods_arbo as $value) {
			// verify if product have child then display it after the product name
			$qtyvalue=($value['globalqty']==1? 1 :$object->qty_planned);
			$tmpChildArbo=$object->getChildsArbo($value['id']);
			$nbChildArbo="";
			if (count($tmpChildArbo) > 0) {
				$nbChildArbo=" (".count($tmpChildArbo).")";
				$btopChild = true;
			}
			$nbcolspan=3;
			print '<tr>';
			if ($object->fk_statut ==0) {
				print '<td align="left">';
				if ($action=="editline" && $lineid==$value['rowid']) {
					if ((int)DOL_VERSION > 14) 
						print img_picto($langs->trans('Save'), 'fontawesome_save_far_green_1.5em', $moreatt = 'onclick="document.forms[\'listcomponent\'].submit();"');
					else
						print img_picto($langs->trans('Save'), 'save.png', $moreatt = 'onclick="document.forms[\'listcomponent\'].submit();"');
				}
				else {
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=editline&lineid='.$value['rowid'].'&id='.$object->id.'">';
					print img_edit($langs->trans('Modify'), 1).'</a>';
				}
				print '</td>';
			}
	

			if (!empty($arrayfields['ref']['checked'])) {
				print '<td align="left">';
				if ($action=="editline" && $lineid==$value['rowid']) {
					// selection du produit
					// on n'affiche pas le stock (on s'en fout ici)
					$tmpstock=$conf->stock->enabled;
					$conf->stock->enabled=null;
					print $form->select_produits($value['id'],'fk_product');
					$conf->stock->enabled=$tmpstock;
				} else {
					print $object->getNomUrlFactory($value['id'], 1, 'fiche').$nbChildArbo;
					print $object->PopupProduct($value['id']);
				}
				print '</td>';
			}
			if (!empty($arrayfields['label']['checked'])) {
				print '<td align="left" title="'.$value['description'].'">';
				print $value['label'];
				print '</td>';
			}
			if (!empty($arrayfields['nb']['checked'])) {
				print '<td align="center">';
				if ($action=="editline" && $lineid==$value['rowid']) {
					print '<input type=text size=1 name=qtyplanned value="'.$value['qtyplanned'].'">';
					// la gestion de la quantité globale aussi?
					print '<input type=checkbox name=globalqty title="'.$langs->trans("Global").'" value=1 '.($value['globalqty']?' tchecked':"").">";
				} else {
					print $value['qtyplanned'];
					if ($value['globalqty'] == 1)
						print "&nbsp;G";
				}
				print '</td>';
			}
			if (!empty($conf->stock->enabled)) {
				$nbcolspan+=1;

				if ($value['type']==0 || empty($value['type'])) {
					// if product
					$nbcolspan+=1;
					$product->fetch($value['id']);
					$product->load_stock();
					$stockEntrepot =0;
					if (!empty($product->stock_warehouse))
						$stockEntrepot = $product->stock_warehouse[$object->fk_entrepot]->real;
					if (!empty($arrayfields['realstock']['checked'])) {
						if ((! empty($conf->productbatch->enabled)) && $product->hasbatch()) {
							$details= $product->stock_warehouse[1]->detail_batch;
							print '<td align=center>';
							if ($details<0) 
								dol_print_error($db);
							foreach ($details as $pdluo) {
								//print 'Caducidad '. dol_print_date($pdluo->eatby,'day') .',';
								//print 'Venta máxima '. dol_print_date($pdluo->sellby,'day') .',';
								print 'Lote '.$pdluo->batch.',';
								print ' Stock '.$pdluo->qty;
								print '<br>';
							}
							print '</td>';
						} else {
							print '<td align=center>' . $object->getUrlStock($value['id'], 1, $product->stock_reel) . '</td>';
						}
					}
					if (!empty($arrayfields['warehousestock']['checked'])) 
						print '<td align=right>' .$stockEntrepot . '</td>';

					if (!empty($arrayfields['qtyorder']['checked'])) {
						$nbcmde=0;
						// on regarde si il n'y pas de commande fournisseur en cours
						$sql = 'SELECT DISTINCT sum(cofd.qty) as nbCmdFourn';
						$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cofd";
						$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseur as cof ON cof.rowid = cofd.fk_commande";
						$sql.= " WHERE cof.entity = ".$conf->entity;
						$sql.= " AND cof.fk_statut = 3";
						$sql.= " and cofd.fk_product=".$value['id'];
						//print $sql;
						$resql = $db->query($sql);
						if ($resql) {
							$objpcmd = $db->fetch_object($resql);
							if ($objpcmd->nbCmdFourn)
								$nbcmde=$objpcmd->nbCmdFourn;
						}
						print '<td align=right>'.$nbcmde.'</td>';
					}
					if (!empty($arrayfields['qtyOk']['checked'])) {
						print '<td align=right>';
						// pour gêrer les niveaux d'alertes
						$qteNeed= $value['qtyplanned']; // La valeur est totale
						$qtyRestante = $stockEntrepot - $qteNeed;
						if ($qtyRestante < 0) {
							// pas assez en entrepot on vérifie au global
							$qtyRestanteGlobal = $product->stock_reel - $qteNeed;
							if ($qtyRestanteGlobal<0) {
								print '<font color="red"><b>'."KO".'</b></font>';
							} else {
								print '<font color="orange"><b>'."KO".'</b></font>';
							}
						}
						else // là on est OK
							print '<font color="green"><b>'."OK".'</b></font>';
						print '</td>';
					}
				}
				else {
					// no stock management for services
					if (!empty($arrayfields['realstock']['checked'])) 
						print '<td></td>';
					if (!empty($arrayfields['warehousestock']['checked'])) 
						print '<td></td>';
					if (!empty($arrayfields['qtyorder']['checked'])) 
						print '<td></td>';
					if (!empty($arrayfields['qtyOk']['checked'])) 
						print '<td></td>';
				}
			}

			// display else vwap or else latest purchasing price
			if (!empty($arrayfields['unitpmp']['checked']))  {
				print '<td align=right>';
				if ($action=="editline" && $lineid==$value['rowid']) {
					print '<input type=text size=3 name=pmp value="'.price2num($value['pmp']).'">';
				} else
					print price($value['pmp'], 0, '', 1, 2, 2); 
				print '</td>';
			}
			
			if (!empty($arrayfields['costpmpht']['checked'])) 
				print '<td align="right">'.price($value['pmp']*$value['nb']*$qtyvalue, 0, '', 1, 2, 2).'</td>'; 
			
			if (!empty($arrayfields['unitpriceht']['checked'])) {
				print '<td align=right>';
				if ($action=="editline" && $lineid==$value['rowid']) {
					print '<input type=text size=3 name=price value="'.price2num($value['price']).'">';
				} else
					print price($value['price'], 0, '', 1, 2, 2); 
				print '</td>';
			}


			//	print '<td align="right">'.price($value['price']*$value['nb']*$qtyvalue, 0, '', 1, 2, 2).'</td>';
			if (!empty($arrayfields['sellingpriceht']['checked'])) 
				print '<td align="right">'.price(($value['price']-$value['pmp'])*$value['nb'], 0, '', 1, 2, 2).'</td>'; 

			if (!empty($arrayfields['profitamount']['checked'])) 
				print '<td align="right">'.price(($value['price']-$value['pmp'])*$value['nb']*$qtyvalue, 0, '', 1, 2, 2).'</td>'; 
			$mntTot = $mntTot + $value['price'] * $value['nb'] * $qtyvalue;
			$pmpTot = $pmpTot + $value['pmp'] * $value['nb'] * $qtyvalue; // sub total calculation


			if (!empty($arrayfields['weight']['checked'])) {
				// détermination du poids
				$weightunits= $product->weight_units;

				if ($weightunits < 50) {
					// modification en V11
					if ((int) DOL_VERSION > 10)
						$trueWeightUnit=pow(10, $weightunits);
					else
						$trueWeightUnit=pow(10, $weightunits - 2);
					$productWeight=$product->weight * $qtyvalue * $trueWeightUnit;
				} else {
					if ($weightunits == 99) {
						// conversion 1 Livre = 0.45359237 KG
						$trueWeightUnit = 0.45359237;
					} elseif ($weightunits == 98) {
						// conversion 1 once = 0.0283495 KG
						$trueWeightUnit = 0.0283495;
					} else {
						// This may be wrong if we mix different units
						$trueWeightUnit = 1;
					}

					$productWeight=$product->weight * $qtyvalue * $trueWeightUnit;
				}
				print '<td align="right">'.price($productWeight,2).' Kg</td>'; 
				$TotWeight += $productWeight;
			}

			if (!empty($arrayfields['ordercomponent']['checked'])) {
				print '<td align=right>';
				if ($action=="editline" && $lineid==$value['rowid']) {
					print '<input type=text size=1 name=ordercomponent value="'.$value['ordercomponent'].'">';
				} else
					print $value['ordercomponent'];
				print '</td>';
			} else 
				print '<input type=hidden  name=ordercomponent value="'.$value['ordercomponent'].'">';

			// Extra fields
			$extrafieldsobjectkey=$ExtrafieldTable;
			// on récupère les values des extrafields
			$sql = "SELECT ef.rowid";
			if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])) {
				foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $val) 
					$sql .= ($extrafields->attributes[$extrafieldsobjectkey]['type'][$key] != 'separate' ? ", ef.".$key.' as options_'.$key : '');
			}
			$sql.= " FROM ".MAIN_DB_PREFIX."factorydet_extrafields as ef";
			$sql.= ' , '.MAIN_DB_PREFIX.'factorydet as pf';
			$sql.= ' WHERE pf.rowid = ef.fk_object';
			$sql.= ' AND fk_factory = '.$id;
			$sql.= ' AND fk_product = '.$value['id'];

			$resql = $db->query($sql);
			if ($resql)
				$obj = $db->fetch_object($resql);

			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

			print '<td></td></tr>';

			//print '</tr>';

		}
		// only if not canceled
		if ($user->rights->factory->showprice && $object->qty_planned > 0) {
			print '<tr class="liste_total">';
			//détermination du nombre de colonne affiché
			print '<td colspan='.$nbcol.' align=right >'.$langs->trans("Total").'</b></td>';

			if (!empty($arrayfields['costpmpht']['checked'])) {
				print '<td align="right" ><b>'.price($pmpTot/($object->qty_planned), 0, '', 1, 2, 2).'</b></td>';
			}
	
			if (!empty($arrayfields['unitpriceht']['checked'])) {
				print '<td align="right" ><b>'.price($pmpTot/($object->qty_planned), 0, '', 1, 2, 2).'</b></td>';
			}
			if (!empty($arrayfields['sellingpriceht']['checked'])) {
				print '<td align="right" ><b>'.price($pmpTot, 0, '', 1, 2, 2).'</b></td>';
			}
	
			if (!empty($arrayfields['profitamount']['checked'])) {
				print '<td align="right" ><b>'.price(($mntTot-$pmpTot), 0, '', 1, 2, 2).'</b></td>';
			}
	
			if (!empty($arrayfields['weight']['checked'])) {
				print '<td></td>';
			}
			if (!empty($arrayfields['ordercomponent']['checked'])) {
				print '<td></td>';
			}
			if (!empty($arrayfields['description']['checked'])) {
				print '<td></td>';
			}

			//print '<td align="right" ><b>'.price($mntTot, 0, '', 1, 2, 2).'</b></td>';
			//print '<td align="right" ><b>'.price(($mntTot-$pmpTot)/($object->qty_planned), 0, '', 1, 2, 2).'</b></td>';

			// colonnes pour les extrafields
			if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])
				&& is_array($extrafields->attributes[$ExtrafieldTable]['label']) 
				&& count($extrafields->attributes[$ExtrafieldTable]['label']) > 0) {
				print "<td colspan=".count($extrafields->attributes[$ExtrafieldTable]['label'])."></td>";
			}
			print '<td></td></tr>';
		}
		print '</table>';
		print '</form>';

	}


	


	$addselected=GETPOST("addselected", 'int');
	$keysearch=GETPOST('keysearch', 'alpha');

	/* Gestion de la composition à chaud */
	if ($action == 'search') {
		
		// selon le filtre sélectionné on filtre
		$sql = 'SELECT DISTINCT p.rowid, p.ref, p.label, p.price, p.fk_product_type as type, p.pmp';
		if (!empty($conf->global->factory_extrafieldsNameInfo))
			$sql.= ' , pe.'.$conf->global->factory_extrafieldsNameInfo. ' as addinforecup';
		else
			$sql.= ' , "" as addinforecup';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'product as p';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields as pe ON p.rowid = pe.fk_object';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON p.rowid = cp.fk_product';
		$sql.= ' WHERE p.entity IN ('.getEntity("product", 1).')';
		$sql.= " AND p.rowid <> ".$productid;		 // pour ne pas afficher le produit lui-m�me
		if ($keysearch != "") {
			$sql.= " AND (p.ref LIKE '%".$keysearch."%'";
			$sql.= " OR p.label LIKE '%".$keysearch."%')";
		}
		if (!empty($conf->categorie->enabled) && $parent != -1 and $parent) {
			$sql.= " AND cp.fk_categorie ='".$db->escape($parent)."'";
		}

		if ($addselected) {
			$sql.= ' UNION SELECT DISTINCT p.rowid, p.ref, p.label, p.price, p.fk_product_type as type, p.pmp';
			if (!empty($conf->global->factory_extrafieldsNameInfo))
				$sql.= ' , pe.'.$conf->global->factory_extrafieldsNameInfo.' as addinforecup';
			else
				$sql.= ' , "" as addinforecup';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'product as p';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields as pe ON p.rowid = pe.fk_object';
			$sql.= ' , '.MAIN_DB_PREFIX.'product_factory as pf WHERE pf.fk_product_children = p.rowid';
			$sql.= ' AND p.entity IN ('.getEntity("product", 1).')';
			$sql.= " AND pf.fk_product_father = ".$productid;		 // pour afficher les produits d�j� s�lectionn�s
		}	

		$resql = $db->query($sql);

		$productstatic = new Product($db);
	}

	$rowspan=1;
	if (!empty($conf->categorie->enabled)) 
		$rowspan++;

	if ($action == 'edit' || $action == 'search' || $action == 're-edit' ) {
		print '<br>';
		print load_fiche_titre($langs->trans("ProductToAddSearch"), '', '');
		print '<form action="fiche.php?id='.$id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<table class="border" width="50%"><tr><td>';
		print '<table class="nobordernopadding" width="100%">';

		print '<tr><td>';
		print $langs->trans("KeywordFilter").' &nbsp; ';
		print '</td>';
		print '<td><input type="text" name="keysearch" value="'.$keysearch.'">';
		print '<input type="hidden" name="action" value="search">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '</td>';
		print '<td rowspan="'.$rowspan.'"  valign="bottom">';
		print '<input type="checkbox" name=addselected '.($addselected?"checked":"").' value="1">';
		print $langs->trans("AddSelectectProduct").'<br>';
		print '<input type="submit" class="button" value="'.$langs->trans("Search").'">';
		print '</td></tr>';
		if (!empty($conf->categorie->enabled)) {
			print '<tr><td>'.$langs->trans("CategoryFilter").' &nbsp; </td>';
			print '<td>'.$form->select_all_categories(0, $parent).'</td></tr>';
		}

		print '</table>';
		print '</td></tr></table>';
		print '</form>';

		if ($action == 'search') {
			print '<br>';
			print '<form action="fiche.php?id='.$id.'" method="post">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="add_prod">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			print '<table class="nobordernopadding" width="100%">';
			print '<tr class="liste_titre">';
			print '<th class="liste_titre">'.$langs->trans("Ref").'</th>';
			print '<th class="liste_titre">'.$langs->trans("Label").'</th>';
			print '<th class="liste_titre" align="right">'.$langs->trans("BuyPrice").'</th>';
			print '<th class="liste_titre" align="right">'.$langs->trans("SellPrice").'</th>';
			print '<th class="liste_titre" align="center">'.$langs->trans("AddDel").'</th>';
			print '<th class="liste_titre" align="right">'.$langs->trans("Quantity").'</th>';
			print '<th class="liste_titre" align="right">'.$langs->trans("Global").'</th>';
			print '</tr>';
			if ($resql) {
				$num = $db->num_rows($resql);
				$i=0;
				$var=true;

				if ($num == 0) 
					print '<tr><td colspan="4">'.$langs->trans("NoMatchFound").'</td></tr>';

				while ($i < $num) {
					$objp = $db->fetch_object($resql);

					print "\n<tr >";
					$productstatic->id=$objp->rowid;
					$productstatic->ref=$objp->ref;
					$productstatic->libelle=$objp->label;
					$productstatic->type=$objp->type;

					print '<td>'.$object->getNomUrlFactory($objp->rowid, 1, 'index', 24);
					print $object->PopupProduct($objp->rowid, $i);
					print '</td>';
					$labeltoshow=$objp->label;
					//if ($conf->global->MAIN_MULTILANGS && $objp->labelm) $labeltoshow=$objp->labelm;

					print '<td>';
					print "<a href=# onclick=\"$('.detailligne".$i."').toggle();\" >";
					print img_picto("", "opendown@factory")."</a>&nbsp;";
					print $labeltoshow.'</td>';
					if ($object->is_sousproduitOF($id, $objp->rowid)) {
						$addchecked = ' checked="checked"';
						$qty = $object->is_sousproduit_qty;
						$descComposant = $object->is_sousproduit_description;
						$ordercomponent=$object->is_sousproduit_ordercomponent;
						$qtyglobal=$object->is_sousproduit_qtyglobal;
					} else {
						$addchecked = '';
						$qty = "1";
						$descComposant = '';
						$ordercomponent="0"; // par défaut pas d'ordre
						$qtyglobal=0;

					}
					print '<td align="right">'.price($objp->pmp).'</td>';
					print '<td align="right">'.price($objp->price).'</td>';

					print '<td align="center"><input type="hidden" name="prod_id_'.$i.'" value="'.$objp->rowid.'">';
					print '<input type="checkbox" '.$addchecked.'name="prod_id_chk'.$i.'" value="'.$objp->rowid.'"></td>';
					print '<td align="right"><input type="text" size="3" name="prod_qty_'.$i.'" value="'.$qty.'">';
					print '</td><td align="right">';
					print $form->selectyesno('prod_id_globalchk'.$i, $qtyglobal, 1);
					print '</td></tr>';
					print "<tr class='detailligne".$i."' style='display:none'>";
					print '<td></td>';

					print '<td valign=top align=left >'.$langs->trans("Position").' : ';
					print '<input type="text" size="2" name="prod_order_'.$i.'" value="'.$ordercomponent.'">';
					print '</td>';
					print '<td valign=top align=left colspan=2>'.$langs->trans("InfoAnnexes").' <br>';
					print '<textarea name="descComposant'.$i.'" wrap="soft" cols="70" rows="'.ROWS_3.'">';
					print $descComposant.'</textarea>';

					// ici les extrafields en saisie
					print '<td valign=top align=left colspan=5>';
					//extrafields_edit.tpl
					$ExtrafieldTable="factorydet";
					if (!empty($extrafields->attributes[$ExtrafieldTable]['label']) 
						&& is_array($extrafields->attributes[$ExtrafieldTable]['label']) 
						&& count($extrafields->attributes[$ExtrafieldTable]['label']) > 0) {
						print '<table>';
						foreach ($extrafields->attributes[$ExtrafieldTable]['label'] as $key => $val) {
							print '<tr><td>'.$extrafields->attributes[$ExtrafieldTable]['label'][$key];
							print ' </td><td>: ';
							// on récupère les values des extrafields pour ce champs
							$sql = "SELECT ef.* FROM ".MAIN_DB_PREFIX."factorydet_extrafields as ef";
							$sql.= ' , '.MAIN_DB_PREFIX.'factorydet as fd';
							$sql.= ' WHERE fd.rowid = ef.fk_object';
							$sql.= ' AND fk_factory = '.$id;
							$sql.= ' AND fk_product = '.$objp->rowid;
							$resqlef = $db->query($sql);
							if ($resqlef)
								$obj = $db->fetch_object($resqlef);
							else
								print $sql."<br>";
							print '<input type=hidden name="extrafieldsid_'.$i.'" value="'.($obj->rowid?$obj->rowid:0).'">';
							print $extrafields->showInputField($key, $obj->$key, '', '_'.$i, '', 0, $i, $ExtrafieldTable);
							print '</td></tr>';
							
						}
						print '</table>';
					}
					print '</td>';
					print '</tr>';
					$i++;
				}
			} else 
				dol_print_error($db);

			print '</table>';
			print '<input type="hidden" name="max_prod" value="'.$i.'">';

			if ($num > 0) {
				print '<br><center>';
				print '<input type="submit" class="button" value="'.$langs->trans("Add").'/'.$langs->trans("Update").'">';
				print ' &nbsp; &nbsp; <input type="submit" name="cancel" class="button" value="'.$langs->trans("Cancel").'">';
				print '</center>';
			}
			print '</form>';
		}
	}

} else {
	// on affiche la liste des produit sur lequel on souhaite créer un OF
	print load_fiche_titre($langs->trans("NewOrderBuild"));

	print '<form name="factory" action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<input type="hidden" name="action" value="create">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<table class="nobordernopadding" width="100%">';

	// si en V11 et qu'il y a des BOM
	if ((int) DOL_VERSION > 10 && !empty($conf->global->MAIN_MODULE_BOM)) {
		require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
		$product_bom = new Product($db);
		$bom_static = new BOM($db);
		// on ne sélectionne que les bom actif
		$tblBomList = $bom_static->fetchAll('', '', 0, 0, array("status" =>1));
		
		if (count($tblBomList) > 0 ) {
			$bomArray = array();
			foreach($tblBomList as $bomElem) {
				$product_bom->fetch($bomElem->fk_product);
				$bomArray[$bomElem->id] =  $bomElem->ref ." / ".$product_bom->ref;
			}
			print '<tr><td >'.$langs->trans("BOMList").'</td><td align=left>';
			
			print $form->selectarray("bomid", $bomArray, "", 1);
			print '</td></tr>';
		}
		print '<tr><td class="fieldrequired">'.$langs->trans("Products").'</td><td align=left>';
	}
	else
		print '<tr><td class="fieldrequired">'.$langs->trans("Products").'</td><td align=left>';

		// seulement les produits fabricables
	$factoryproductarray =$object->getListProductWithComposition();
	print $form->selectarray("productid", $factoryproductarray, "", 1);

	print '</td></tr>';

	print '<tr><td class="fieldrequired" width=250px>'.$langs->trans("EntrepotStock").'</td><td >';
	print $formProduct_static->selectWarehouses("", "entrepotid", 'warehouseopen');
	print '</td></tr>';
	print '<tr><td class="fieldrequired" >'.$langs->trans("QtyToBuild").'</td>';
	print '<td  ><input style="text-align:right;" type="text" name="nbToBuild" size=5 value="'.GETPOST("nbToBuild").'">';
	print '</td></tr>';
	
	print '<tr><td>'.$langs->trans("FactoryDateStartPlanned").'</td>';
	print '<td >';
	$plannedstart=dol_mktime(
					GETPOST('plannedstarthour', 'int'), GETPOST('plannedstartmin', 'int'), 0,
					GETPOST('plannedstartmonth', 'int'), GETPOST('plannedstartday', 'int'), 
					GETPOST('plannedstartyear', 'int')
	);
	print $form->selectDate(
					(GETPOST("plannedstart")? $plannedstart:''), 'plannedstart', 
					1, 1, '', "plannedstart"
	);
	print '</td></tr>';

	print '<tr><td>'.$langs->trans("FactoryDateEndBuildPlanned").'</td>';
	print '<td >';
	$plannedend=dol_mktime(
					GETPOST('plannedendhour', 'int'), GETPOST('plannedendmin', 'int'), 0,
					GETPOST('plannedendmonth', 'int'), GETPOST('plannedendday', 'int'), 
					GETPOST('plannedendyear', 'int')
	);
	print $form->selectDate(
					(GETPOST("plannedend")? $plannedend:''), 'plannedend', 
					1, 1, '', "plannedend"
	);
	print '</td></tr>';
	
	print '<tr><td>'.$langs->trans("FactoryDurationPlanned").'</td>';
	print '<td>';
	print $form->select_duration(
					'workload', (int) GETPOST("workloadhour")*3600+ (int) GETPOST("workloadmin","int")*60, 0, 'text'
	);
	print '</td></tr>';

	print '</table>';
	print '</td></tr>';

	print '<tr><td colspan=2 align=center>';
	print '<br><input type="submit" class="butAction" name="verifyof" value="'.$langs->trans("VerifyQty").'">';
	print '</td></tr>';
	print '</table>';
	print '</form>';

}

/* Barre d'action				*/
if ($action == '' && ($id || $ref)) {
	print '<div class="tabsAction">';
	// if ($btopChild){ 
	// 	print '<div style="text-align:left;">';
	// 	print '<b>'.$langs->trans("FactoryTableInfo").'</b>';
	// 	print '</div>';
	// }	
	$parameters = array();
	// Note that $action and $object may have been
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); 
	// modified by hook
	if (empty($reshook)) {
		print '<div class="inline-block divButAction">';

		if ($user->rights->factory->creer && $object->statut == 0) {
			print '<a class="butAction" href="fiche.php?action=edit&id='.$id.'">'.$langs->trans("ChangeGlobalQtyFactory").'</a>';
			print '<a class="butAction" href="fiche.php?action=getdefaultprice&id='.$id.'">'.$langs->trans("GetDefaultPrice").'</a>';
			print '<a class="butAction" href="fiche.php?action=adjustprice&id='.$id.'">'.$langs->trans("AdjustPrice").'</a>';
			print '<br>';
		}

		if ($user->rights->factory->send)
			print '<a class="butAction" href="fiche.php?action=presend&id='.$id.'&mode=init">';
		else
			print '<a class="butActionRefused" href="#">';
		print $langs->trans('SendByMail').'</a>';
		
		if ($user->rights->factory->creer)
			print '<a class="butAction" href="fiche.php?action=clone&id='.$id.'">'.$langs->trans("CloneOF").'</a>';

		if ($user->rights->factory->annuler && $object->statut == 0)
			print '<a class="butAction" href="fiche.php?action=cancelof&id='.$id.'">'.$langs->trans("CancelFactory").'</a>';
		
		print '</div>';
		print '</div>';
		// seulement is on est sur un OF
		print '<div class="fichecenter"><div class="fichehalfleft">';
		/*
		* Documents generes
		*/
		$comref = dol_sanitizeFileName($object->ref);
		//$file = $conf->factory->dir_output.'/'.$comref.'/'.$comref.'.pdf';
		//$relativepath = $comref.'/'.$comref.'.pdf';

		$filedir = $conf->factory->dir_output.'/'.$comref;
		$urlsource=$_SERVER["PHP_SELF"]."?id=".$object->id;
		$genallowed=$user->rights->factory->creer;
		$delallowed=$user->rights->factory->supprimer;
		
		print $formfile->showdocuments(
						'factory', $comref, $filedir, $urlsource, 
						$genallowed, $delallowed, $object->modelpdf, 
						1, 0, 0, 28, 0, '', '', '', ''
		);

		/*
		* Linked object block
		*/
		$somethingshown = $form->showLinkedObjectBlock($object, "");

		print '</div><div class="fichehalfright"><div class="ficheaddleft">';

		// si l'of a des sous-composant on affiche l'arboresencence ici
		if ($btopChild)	{
			print load_fiche_titre($langs->trans("Treeview"), '', '');
			gen_ulArbo($object->fk_product, $id, $object->qty_planned);
		}


		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions=new FormActions($db);
		$somethingshown=$formactions->showactions($object, 'factory');

		print '</div></div>';
	}
	
	print '</div>';	
	
} elseif ($action == 'adjustprice') {
	print '<br>';
	print load_fiche_titre($langs->trans("AdjustPrice"), '', '');

	print '<form action="fiche.php?id='.$id.'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="updateprice">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<table class="nobordernopadding" >';
	print '<tr class="liste_titre">';
	print '<th width="100px" class="liste_titre">'.$langs->trans("Ref").'</th>';
	print '<th width="200px" class="liste_titre">'.$langs->trans("Label").'</th>';
	print '<th width="50px" class="liste_titre" align="right">'.$langs->trans("Quantity").'</th>';
	print '<th width="100px" class="liste_titre" align="right">'.$langs->trans("InfoPMP").'</th>';
	print '<th width="100px" class="liste_titre" align="right">'.$langs->trans("InfoCostPrice").'</th>';
	print '<th width="100px" class="liste_titre" align="right">'.$langs->trans("BuyPrice").'</th>';
	print '<th width="100px" class="liste_titre" align="right">'.$langs->trans("InfoSellPrice").'</th>';
	print '<th width="100px" class="liste_titre" align="right">'.$langs->trans("SellPrice").'</th>';

	print '</tr>';

	$productstatic = new Product($db);

	foreach ($prods_arbo as $value) {
		$productstatic->id=$value['id'];
		$productstatic->fetch($value['id']);
		$productstatic->type=$value['type'];

		print "\n<tr >";

		print '<td>'.$object->getNomUrlFactory($value['id'], 1, 'fiche', 24).'</td>';
		$labeltoshow=$productstatic->label;

		print '<td>'.$labeltoshow.'</td>';

		//var_dump($value);
		$qty=$value['qtyplanned'];

		print '<td align="right">'.$qty.'</td>';

		print '<td align="right">'.price($productstatic->pmp).'</td>';
		print '<td align="right">'.price($productstatic->cost_price).'</td>';
		print '<td align="right">';
		print '<input type="text" size="5" name="prod_pmp_'.$value['id'].'" value="'.price2num($value['pmp']).'"></td>';

		print '<td align="right">'.price($productstatic->price).'</td>';
		print '<td align="right">';
		print '<input type="text" size="5" name="prod_price_'.$value['id'].'" value="'.price2num($value['price']).'"></td>';

		print '</tr>';
	}

	print '</table>';
	print '<input type="hidden" name="max_prod" value="'.$i.'">';

	print '<div class="tabsAction">';
	print ' <input type="submit" class="butAction" value="'.$langs->trans("Update").'">';
	print ' <input type="submit" class="butAction" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';	
	print '</form>';
}


/*
 * Add file in email form
*/
if (GETPOST('addfile')) {

	// Set tmp user directory TODO Use a dedicated directory for temp mails files
	$vardir=$conf->user->dir_output."/".$user->id;
	$upload_dir_tmp = $vardir.'/temp';

	dol_add_file_process($upload_dir_tmp, 0, 0);
	$action ='presend';
}

/*
 * Remove file in email form
*/
if (GETPOST('removedfile')) {

	// Set tmp user directory
	$vardir=$conf->user->dir_output."/".$user->id;
	$upload_dir_tmp = $vardir.'/temp';

	// TODO Delete only files that was uploaded from email form
	dol_remove_file_process(GETPOST('removedfile'), 0);
	$action ='presend';
}

/*
 * Send mail
*/
if ($action == 'send'  && ! GETPOST('addfile')  && ! GETPOST('removedfile')  && ! GETPOST('cancel')) {
	$langs->load('mails');

	if ($id > 0) {
		$ref = dol_sanitizeFileName($object->ref);
		$file = $conf->factory->dir_output.'/'.$ref.'/'.$ref.'.pdf';
		if (is_readable($file)) {
			if (GETPOST('sendto')) {
				// Le destinataire a ete fourni via le champ libre
				$sendto = GETPOST('sendto');
				$sendtoid = 0;
			} elseif (GETPOST('receiver') != '-1') {
				// Recipient was provided from combo list
				if (GETPOST('receiver') == 'thirdparty') {
					// Id of third party
					$sendto = $object->client->email;
					$sendtoid = 0;
				} else {
					// Id du contact
					//$sendto = $object->client->contact_get_property(GETPOST('receiver'),'email');
					$sendtoid = GETPOST('receiver');
				}
			}

			if (dol_strlen($sendto)) {
				$langs->load("commercial");

				$from = GETPOST('fromname') . ' <' . GETPOST('frommail') .'>';
				$replyto = GETPOST('replytoname'). ' <' . GETPOST('replytomail').'>';
				$message = GETPOST('message');
				$sendtocc = GETPOST('sendtocc');
				$deliveryreceipt = GETPOST('deliveryreceipt');
	
				if ($action == 'send') {
					if (dol_strlen(GETPOST('subject'))) 
						$subject=GETPOST('subject');
					else 
						$subject = $langs->transnoentities('Order').' '.$object->ref;
					$actiontypecode='AC_COM';
					$actionmsg = $langs->transnoentities('MailSentBy').' '.$from.' '.$langs->transnoentities('To').' '.$sendto.".\n";
					if ($message) {
						$actionmsg.=$langs->transnoentities('MailTopic').": ".$subject."\n";
						$actionmsg.=$langs->transnoentities('TextUsedInTheMessageBody').":\n";
						$actionmsg.=$message;
					}
					$actionmsg2=$langs->transnoentities('Action'.$actiontypecode);
				}
	
				// Create form object
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
				$formmail = new FormMail($db);
	
				$attachedfiles=$formmail->get_attached_files();
				$filepath = $attachedfiles['paths'];
				$filename = $attachedfiles['names'];
				$mimetype = $attachedfiles['mimes'];



				// Send mail
				require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
				$mailfile = new CMailFile(
								$subject, $sendto, $from, $message, $filepath, 
								$mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1
				);
				if ($mailfile->error)
					$mesg='<div class="error">'.$mailfile->error.'</div>';
				else {
					$result = $mailfile->sendfile();
					if ($result) {
						$mesg = $langs->trans(
										'MailSuccessfulySent',
										$mailfile->getValidAddress($from, 2),
										$mailfile->getValidAddress($sendto, 2)
						);	// Must not contains "
						$error=0;
	
						// Initialisation donnees
						$object->sendtoid			= $sendtoid;
						$object->actiontypecode	= $actiontypecode;
						$object->actionmsg			= $actionmsg;
						$object->actionmsg2		= $actionmsg2;
						$object->fk_element		= $object->id;
						$object->elementtype		= $object->element;
	
						// Appel des triggers
						include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
						$interface=new Interfaces($db);
						$result=$interface->run_triggers('FACTORY_SENTBYMAIL', $object, $user, $langs, $conf);
						if ($result < 0) {
							$error++; $this->errors=$interface->errors;
						}
						// Fin appel triggers
	
						if ($error) {
							dol_print_error($db);
						} else {
							// Redirect here
							// This avoid sending mail twice if going out and then back to page
							//header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id.'&mesg='.urlencode($mesg));
							//exit;
						}
					} else {
						$langs->load("other");
						$mesg='<div class="error">';
						if ($mailfile->error) {
							$mesg.=$langs->trans('ErrorFailedToSendMail', $from, $sendto);
							$mesg.='<br>'.$mailfile->error;
						} else {
							$mesg.='No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
						}
						$mesg.='</div>';
					}
				}
			} else {
			$langs->load("other");
			$mesg='<div class="error">'.$langs->trans('ErrorMailRecipientIsEmpty').' !</div>';
			$action='presend';
			dol_syslog('Recipient email is empty');
			}
		} else {
			$langs->load("errors");
			$mesg='<div class="error">'.$langs->trans('ErrorCantReadFile', $file).'</div>';
			dol_syslog('Failed to read file: '.$file);
		}
	} else {
		$langs->load("other");
		$mesg='<div class="error">'.$langs->trans('ErrorFailedToReadEntity', $langs->trans("Order")).'</div>';
		dol_syslog($langs->trans('ErrorFailedToReadEntity', $langs->trans("Order")));
	}
	
	$action="";
}

dol_htmloutput_mesg($mesg);

/*
 * Action presend
*
*/
if ($action == 'presend') {
	$ref = dol_sanitizeFileName($object->ref);
	
	$fileparams = dol_most_recent_file($conf->factory->dir_output . '/' . $ref, preg_quote($ref, '/'));
	$file=$fileparams['fullname'];

	// Build document if it not exists
	if (! $file || ! is_readable($file)) {
		// Define output language
		$outputlangs = $langs;
		$newlang='';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) 
				$newlang=$_REQUEST['lang_id'];
		//if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
		if (! empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
		}
		
		$result=factory_create($db, $object, $object->model_pdf, $outputlangs); //, $hidedetails, $hidedesc, $hideref);

		if ($result <= 0) {
			dol_print_error($db, $result);
			exit;
		}
		$fileparams = dol_most_recent_file($conf->factory->dir_output.'/'.$ref, preg_quote($ref, '/'));
		$file=$fileparams['fullname'];
	}
	//var_dump($file);

	print '<br>';
	print_titre($langs->trans('SendFactoryByMail'));

	// Cree l'objet formulaire mail
	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
	$formmail = new FormMail($db);
	$formmail->fromtype = 'user';
	$formmail->fromid   = $user->id;
	$formmail->fromname = $user->getFullName($langs);
	$formmail->frommail = $user->email;
	$formmail->withfrom=1;
	
	// on récupère les contacts de l'entrepot
	$liste=array();
	foreach ($object->contact_entrepot_email_array() as $key=>$value)	
		$liste[$key]=$value;

	$formmail->withto=GETPOST('sendto')?GETPOST('sendto'):$liste;
	$formmail->withtocc=$liste;
	$formmail->withtoccc=!empty($conf->global->MAIN_EMAIL_USECCC)?$conf->global->MAIN_EMAIL_USECCC:"";
	$formmail->withtopic=$langs->trans('SendFactoryRef', '__FACTORYREF__');
	$formmail->withfile=2;
	//$formmail->withmaindocfile=1;
	$formmail->withbody=1;
	$formmail->withdeliveryreceipt=1;
	$formmail->withcancel=1;
	// Tableau des substitutions
	$formmail->substit['__FACTORYREF__']=$object->ref;
	$formmail->substit['__SIGNATURE__']=$user->signature;
	$formmail->substit['__USER_SIGNATURE__']=$user->signature;
	//$formmail->substit['__REFCLIENT__']=$object->ref_client;
	$formmail->substit['__PERSONALIZED__']='';
	//$formmail->substit['__CONTACTCIVNAME__']='';

	$custcontact='';
	$contactarr=array();
	$entrepotStatic=new Entrepot($db);
	$entrepotStatic->fetch($object->fk_entrepot);
	$entrepotStatic->element='stock'; // bug dolibarr corrigé dans les prochaines versions
	$contactarr=$entrepotStatic->liste_contact(-1, 'external');
	if (is_array($contactarr) && count($contactarr)>0) {
		foreach ($contactarr as $contact) {
			if ($contact['libelle'] == $langs->trans('TypeContact_entrepot_external')) {
				$contactstatic=new Contact($db);
				$contactstatic->fetch($contact['id']);
				$custcontact=$contactstatic->getFullName($langs, 1);
			}
		}

		if (!empty($custcontact)) {
			$formmail->substit['__CONTACTCIVNAME__']=$custcontact;
		}
	}

	// Tableau des parametres complementaires
	$formmail->param['action']='send';
	$formmail->param['models']='factory_send';
	$formmail->param['factoryid']=$id;
	$formmail->param['returnurl']=$_SERVER["PHP_SELF"].'?id='.$id;

	// Init list of files
	if (GETPOST("mode")=='init') {
		$formmail->clear_attached_files();
		$formmail->add_attached_files($file, basename($file), dol_mimetype($file));
		$formmail->param['fileinit'] =array($file);
	}
	// Show form
	$formmail->show_form();

	print '<br>';
}

llxFooter();
$db->close();

print '<script>$(function(){';
//print '$(".tiptipimg").tipTip({maxWidth: "auto", edgeOffset: 10});';
print '$("#browser").treeview({
	collapsed: true,
	animated: "fast",
	persist: "cookie",
	control: "#browsercontrol"
});';

print '});</script>';

function gen_ulArbo($idproduct, $factoryId=0, $qtyinit=1, $logLevel=0)
{
	global  $db, $langs;
	$objectRecursif = new Factory($db);
	//$objectRecursif->fetch($idproduct);
	if ($factoryId >0)
		$prods_arboRecursif =$objectRecursif->getChildsOF($factoryId); 
	else
		$prods_arboRecursif =$objectRecursif->getChildsArbo($idproduct); 
	// List of subproducts
	if (count($prods_arboRecursif) > 0) {
		if ($factoryId > 0) {
			print '<table class="centpercent"><tr class="liste_titre"><td colspan=2><td align=right width=50px>'.$langs->trans("Qty");
			print "</td></tr></table>";
			if ($qtyinit != 1) 
			print $qtyinit." x ";
			print $objectRecursif->getNomUrlFactory($idproduct, 1, 'fiche');
			print '<ul id="browser" class="filetree">';
		} else
			print '<ul>';
		foreach ($prods_arboRecursif as $value) {
			if ($factoryId > 0) {
				$qtyvalue= ($value['globalqty']==1? 1 : $qtyinit) ;
				print '<li><table class="centpercent"><tr><td width=160px>';
				print $objectRecursif->getNomUrlFactory($value['id'], 1, 'fiche');
				print '</td><td align=left>'.$value['label'];
				if (count($value['composed'])== 0)
					print "</td><td width=50px align=right>". $value['qtyplanned'];
				print "</td></tr></table>";

				gen_ulArbo($value['id'], 0, $qtyvalue, $logLevel+1);
				print '</li>';
			} else {
				$qtyvalue=($value[6]==1? 1 :$value[1]) * $qtyinit;
				$tabs = 160 - 21 * $logLevel;
				print '<li><table class="centpercent"><tr><td width='.$tabs.'px>';
				if ($value[1] != 1) 
					print $value[1]." x ";
				print $objectRecursif->getNomUrlFactory($value[0], 1, 'fiche');

				print '</td><td align=left>'.$value[3];
				if (count($value[9])== 0)
					print "</td><td width=50px align=right>".($qtyvalue);
				print "</td></tr></table>";
				gen_ulArbo($value[0], 0, $qtyvalue, $logLevel+1);
				print '</li>';
			}

		}
		print '</ul>';
	}

}