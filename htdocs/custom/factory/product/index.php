<?php
/* Copyright (C) 2001-2007	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2011		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2013-2022	Charlene BENKE			<charlene@patas-monkey.com>
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
 *  \file	   htdocs/factory/product/index.php
 *  \ingroup	product
 *  \brief	  Page de définition de la fabrication
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) 
	$res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) 
	$res=@include("../../../main.inc.php");	// For "custom" directory


require_once DOL_DOCUMENT_ROOT."/core/lib/product.lib.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";

if ((int) DOL_VERSION > 10 && !empty($conf->global->MAIN_MODULE_BOM) ) {
	require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
}

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

$langs->load("bills");
$langs->load("products");

$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$parent=GETPOST('parent');

$lineid=GETPOST('lineid','int');
$addselected=GETPOST("addselected", "alpha");
$keysearch=GETPOST('keysearch', 'alpha');


// Security check
if (! empty($user->socid)) $socid=$user->socid;
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
$result=restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);

$mesg = '';

$object = new Product($db);
$factory = new Factory($db);
$entrepot = new Entrepot($db);
$product = new Product($db);

$productid=0;
if ($id || $ref) {
	$result = $object->fetch($id, $ref);
	$productid=$object->id;
	$id=$object->id;
	$factory->id =$id;
}

/*
 * Actions
 */

// add sub-product to a product
if ( $action == 'add_prod' && $cancel <> $langs->trans("Cancel") 
	&& ($user->rights->produit->creer || $user->rights->service->creer)) {
	$error=0;
	for ($i=0;$i<$_POST["max_prod"];$i++) {
		//print "<br> : ".$_POST["prod_id_chk".$i];
		// suppression de l'extrafields précédent si existant
		if (!empty($_POST["extrafieldsid_".$i])) {
			$sql = 'DELETE from '.MAIN_DB_PREFIX.'product_factory_extrafields';
			$sql .= ' WHERE fk_object = '.$_POST["extrafieldsid_".$i];
			$db->query($sql);
		}

		if (!empty($_POST["prod_id_chk".$i])) {
			$newComponentId = $factory->add_component(
							$id, $_POST["prod_id_".$i], $_POST["prod_qty_".$i], 
							0, 0, $_POST["prod_id_globalchk".$i], 
							$_POST["descComposant".$i], $_POST["prod_order_".$i]
			);
			if ($newComponentId > 0) {
				$ExtrafieldTable="product_factory";
				// on met à jour l'extrafields
				$extrafields->fetch_name_optionals_label($ExtrafieldTable);
				// on crée cet objet juste pour accéder à la fonction native de création d'extrafields 
				
				//$extrafieldsElement->fetch_optionals();
				if (is_array($extrafields->attributes[$ExtrafieldTable]['label']) 
				&& count($extrafields->attributes[$ExtrafieldTable]['label']) > 0) {
					$extrafieldsElement = New ProductFactory($db);
					// on aliment les valeurs de l'extrafields
					foreach ($extrafields->attributes[$ExtrafieldTable]['label'] as $key => $val) {
						$extrafieldsElement->array_options["options_".$key]=$_POST["options_".$key."_".$i];
					}
					// on ajoute l'id pour faire le lien avant ajout des valeurs dans l'extrafields
					$extrafieldsElement->id = $newComponentId;
					$extrafieldsElement->insertExtraFields();
				}
				$action = 'edit';
			}
			else {
				$error++;
				$action = 're-edit';
				if ($factory->error == "isFatherOfThis") 
					$mesg.=($mesg ? "<br>" : "").'<div class="error">'.$langs->trans("ErrorAssociationIsFatherOfThis").'</div>';
				else 
					$mesg.=($mesg ? "<br>" : "").$factory->error;
			}
		} else {
			if ($factory->del_component($id, $_POST["prod_id_".$i]) > 0)
				$action = 'edit';
			else {
				$error++;
				$action = 're-edit';
				$mesg.=($mesg ? "<br>" : "").$factory->error;
			}
		}
	}
	if (! $error) {
		header("Location: ".$_SERVER["PHP_SELF"].'?id='.$object->id);
		exit;
	}
}
if ($cancel == $langs->trans("Cancel")) {
	$action = '';
	Header("Location: index.php?id=".$_POST["id"]);
	exit;
}

if ($action == 'getfromvirtual') {	
	// on récupère la fabrication de la composition virtuelle
	$factory->cloneFromVirtual();
	$action="";
}
if ($action == 'getfromvariant') {	
	// on récupère la fabrication de la composition virtuelle
	$product_pere = GETPOST('product_pere', 'int');
	$factory->cloneFromVariant($product_pere);
	$action="";
}

if ($action == 'getdefaultprice') {	
	$factory->getdefaultprice();
	$action="";
}

if ($action == 'updateprice') {
	// on modifie les prix 
	$prodsfather = $factory->getFather(); //Parent Products
	$factory->get_sousproduits_arbo();
	
	// Number of subproducts
	$prods_arbo = $factory->get_arbo_each_prod();
	// something wrong in recurs, change id of object
	$factory->id = $id;

	// List of subproducts
	if (count($prods_arbo) > 0) {
		foreach ($prods_arbo as $value)
			$factory->updatefactoryprices(
							$value['id'], GETPOST("prod_pmp_".$value['id']), 
							GETPOST("prod_price_".$value['id'])
			);
	}
	$action="";
}

if ($action == 'importation') {
	$factory->importComposition(GETPOST("importexport", "none"));
	$action="";
}

if ($action == 'remplacementproduct') {
	$factory->productChange(GETPOST("fk_product","int"),GETPOST("productchange", "array"));
	$action="";
}

if ($action=="updateline") {
	$factoryDet_static = new ProductFactory($db);
	$factoryDet_static->id = GETPOST('lineid', 'int');
	$factoryDet_static->fk_product = GETPOST('fk_product','int');
	$factoryDet_static->qty = GETPOST('qtyplanned','int');
	$factoryDet_static->globalqty = !empty(GETPOST('globalqty', 'int'))?1:0;
	$factoryDet_static->pmp = GETPOST('pmp', 'int');
	$factoryDet_static->price = GETPOST('price', 'int');
	$factoryDet_static->ordercomponent = !empty(GETPOST('ordercomponent', 'int'))?GETPOST('ordercomponent','int'):0;

	if ($factoryDet_static->update($user))
		$mesg = '<div class="info">'.$langs->trans("LineChanged").'</div>';
	else
		$mesg = '<div class="error">'.$langs->trans("ErrorOnUpdateLine").'</div>';
	$action="";
}

/*
 * View
 */

// search products by keyword and/or categorie
if ($action == 'search') {
	
	// filtre sélectionné on filtre
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
	if (!empty($conf->categorie->enabled) && $parent != -1 and $parent) 
		$sql.= " AND cp.fk_categorie ='".$db->escape($parent)."'";

	if ($addselected) {
		$sql.= ' UNION SELECT DISTINCT p.rowid, p.ref, p.label, p.price, p.fk_product_type as type, p.pmp';
		if (!empty($conf->global->factory_extrafieldsNameInfo))
			$sql.= ' , pe.'.$conf->global->factory_extrafieldsNameInfo. ' as addinforecup';
		else
			$sql.= " , '' as addinforecup";
		$sql.= ' FROM '.MAIN_DB_PREFIX.'product as p';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields as pe ON p.rowid = pe.fk_object';
		$sql.= ' , '.MAIN_DB_PREFIX.'product_factory as pf WHERE pf.fk_product_children = p.rowid';

		$sql.= ' AND p.entity IN ('.getEntity("product", 1).')';
		// pour afficher les produits déjà sélectionnés
		$sql.= " AND pf.fk_product_father = ".$productid;
	}
	//$sql.= " ORDER BY p.ref ASC";

	//print $sql;

	$resqlsearch = $db->query($sql);
}
//print $sql;

$productstatic = new Product($db);
$form = new Form($db);

llxHeader("", "", $langs->trans("CardProduct".$product->type));

dol_htmloutput_mesg($mesg);

$head=product_prepare_head($object, $user);
$titre=$langs->trans("CardProduct".$object->type);
$picto=('product');

dol_fiche_head($head, 'factory', $titre, -1, $picto);
$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

if ($id || $ref) {
	if ($result) {
		$bproduit = ($object->isproduct()); 

		dol_banner_tab($object, 'ref', $linkback, ($user->socid?0:1), 'ref');
		$cssclass='titlefield';

		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield" width="100%">';

		// MultiPrix
		if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
			if ($socid) {
				$soc = new Societe($db);
				$soc->id = $socid;
				$soc->fetch($socid);

				print '<tr><td width="25%">'.$langs->trans("SellingPrice").'</td>';

				if ($object->multiprices_base_type["$soc->price_level"] == 'TTC')
					print '<td>'.price($object->multiprices_ttc["$soc->price_level"]);
				else
					print '<td>'.price($object->multiprices["$soc->price_level"]);

				if ($object->multiprices_base_type["$soc->price_level"])
					print ' '.$langs->trans($object->multiprices_base_type["$soc->price_level"]);
				else
					print ' '.$langs->trans($object->price_base_type);
				print '</td></tr>';

				// Prix mini
				print '<tr><td>'.$langs->trans("MinPrice").'</td><td>';
				if ($object->multiprices_base_type["$soc->price_level"] == 'TTC') {
					print price($object->multiprices_min_ttc["$soc->price_level"]).' ';
					print $langs->trans($object->multiprices_base_type["$soc->price_level"]);
				} else {
					print price($object->multiprices_min["$soc->price_level"]).' ';
					print $langs->trans($object->multiprices_base_type["$soc->price_level"]);
				}
				print '</td></tr>';

				// TVA
				print '<tr><td>'.$langs->trans("VATRate").'</td>';
				print '<td>'.vatrate($object->multiprices_tva_tx["$soc->price_level"], true).'</td></tr>';
			} else {
				for ($i=1; $i<=!empty($conf->global->PRODUIT_MULTIPRICES_LIMIT)?$conf->global->PRODUIT_MULTIPRICES_LIMIT:0; $i++) {
					// TVA
					if ($i == 1) {
						// We show only price for level 1
						print '<tr><td>'.$langs->trans("VATRate").'</td>';
						print '<td>'.vatrate($object->multiprices_tva_tx[1], true).'</td></tr>';
					}
					print '<tr><td width="25%">'.$langs->trans("SellingPrice").' '.$i.'</td>';

					if ($object->multiprices_base_type["$i"] == 'TTC')
						print '<td>'.price($object->multiprices_ttc["$i"]);
					else
						print '<td>'.price($object->multiprices["$i"]);

					if ($object->multiprices_base_type["$i"])
						print ' '.$langs->trans($object->multiprices_base_type["$i"]);
					else
						print ' '.$langs->trans($object->price_base_type);
					print '</td></tr>';

					// Prix mini
					print '<tr><td>'.$langs->trans("MinPrice").' '.$i.'</td><td>';
					if ($object->multiprices_base_type["$i"] == 'TTC')
						print price($object->multiprices_min_ttc["$i"]).' '.$langs->trans($object->multiprices_base_type["$i"]);
					else
						print price($object->multiprices_min["$i"]).' '.$langs->trans($object->multiprices_base_type["$i"]);
					print '</td></tr>';
				}
			}
		} else {
			// TVA
			print '<tr><td width="25%">'.$langs->trans("VATRate").'</td>';
			print '<td>'.vatrate($object->tva_tx.($object->tva_npr?'*':''), true).'</td></tr>';
			
			// Price
			print '<tr><td>'.$langs->trans("SellingPrice").'</td><td>';
			if ($object->price_base_type == 'TTC') {
				print price($object->price_ttc).' '.$langs->trans($object->price_base_type);
				$sale="";
			} else {
				print price($object->price).' '.$langs->trans($object->price_base_type);
				$sale=$object->price;
			}
			print '</td></tr>';
		
			// Price minimum
			print '<tr><td>'.$langs->trans("MinPrice").'</td><td>';
			if ($object->price_base_type == 'TTC')
				print price($object->price_min_ttc).' '.$langs->trans($object->price_base_type);
			else
				print price($object->price_min).' '.$langs->trans($object->price_base_type);

			print '</td></tr>';
		}

		print '</table>';
		print '</div>';
		print '<div class="fichehalfright"><div class="ficheaddleft">';

		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield" width="100%">';

		print '<tr><td>'.$form->textwithpicto($langs->trans("NatureOfProductShort"), $langs->trans("NatureOfProductDesc")).'</td>';
		print '<td>'.$object->getLibFinished().'</td></tr>';

		print '<tr><td>'.$langs->trans("PhysicalStock").'</td>';
		print '<td>'.$object->stock_reel.'</td></tr>';

		if (!empty($conf->variants->enabled) && ($object->isProduct() || $object->isService())) {
			$combination = new ProductCombination($db);

			if ($combination->fetchByFkProductChild($object->id) > 0) {
				$prodstatic = new Product($db);
				$prodstatic->fetch($combination->fk_product_parent);

				// Parent product
				print '<tr><td>'.$langs->trans("ParentProduct").'</td><td>';
				print $prodstatic->getNomUrl(1);
				print '</td></tr>';
			}
		}

		print '</table>';
		print '</div>';

		print '</div></div>';
		print '<div style="clear:both"></div>';

		print dol_get_fiche_end();

		// indique si on a déjà une composition de présente ou pas
		$compositionpresente=0;
		
		$head=factory_product_prepare_head($object, $user);
		$titre=$langs->trans("Factory");
		$picto="factory@factory";
		print dol_get_fiche_head($head, 'composition', $titre, -1, $picto);
		//($links, $active, $title, $notab, $picto, $pictoisfullpath, $morehtmlright, $morecss, $limittoshow, $moretabssuffix);

		// pour connaitre les produits composé du produits
		$prodsfather = $factory->getFather(); //Parent Products

		$sortfield=GETPOST("sortfield");
		$sortorder=GETPOST("sortorder");
	
		// pour connaitre les produits composant le produits
		$factory->get_sousproduits_arbo($sortfield, $sortorder);

		// Number of subproducts
		$prods_arbo = $factory->get_arbo_each_prod();
		// something wrong in recurs, change id of object
		$factory->id = $id;
		
		/* ************************************************************************** */
		/*																			*/
		/* Importation / d'une composition											  */
		/*																			*/
		/* ************************************************************************** */
		if ($action == 'importexport') {
			/*
			 * Import/export customtabs
			 */
			print load_fiche_titre($langs->trans("ImportExportComposition"), '', '../img/object_factory.png', 1);
			
			print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="importation">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
			
			print '<table class="border" width="100%">';
		
			print '<tr><td><span class="fieldrequired">'.$langs->trans("FillImportExportData").'</span></td></tr>';
			print '<td><textarea name=importexport cols=132 rows=20>';
			print $factory->getExportComposition($prods_arbo);
			print '</textarea></td></tr>';	
			print '</table>';
			print '<br><center>';
			print '<input type="submit" class="butAction" value="'.$langs->trans("LaunchImport").'">';
			print '</center>';
			print '</form>';
		} else {
			print load_fiche_titre($langs->trans("FactorisedProductsNumber").' : '.count($prods_arbo), '', '../img/object_factory.png',1);
			//($title, $mesg, $picto, $pictoisfullpath, $id);

			// définition des champs à afficher

			$arrayfieldsProduct = array(
				'ref'=>array('label'=>$langs->trans("Ref"), 'checked'=>1),
				'label'=>array('label'=>$langs->trans("ProductLabel"), 'checked'=>1),
			);
			$ExtraTable="product";
			$extrafields->fetch_name_optionals_label($ExtraTable);
			$search_array_options = $extrafields->getOptionalsFromPost($ExtraTable, '', 'search_');
			$extrattributes=$extrafields->attributes;

			if (!empty($extrattributes[$ExtraTable]['label']) 
				&& is_array($extrattributes[$ExtraTable]['label']) 
				&& count($extrattributes[$ExtraTable]['label']) > 0) {
				foreach ($extrattributes[$ExtraTable]['label'] as $key => $val) {
					if (!empty($extrattributes[$ExtraTable]['list'][$key]))
						$arrayfieldsProduct["efp.".$key] = array(
								'label'=>$extrattributes[$ExtraTable]['label'][$key], 
								'checked'=>(($extrattributes[$ExtraTable]['list'][$key] < 0) ? 0 : 1), 
								'position'=>$extrattributes[$ExtraTable]['pos'][$key], 
								'enabled'=>(abs($extrattributes[$ExtraTable]['list'][$key]) != 3 && $extrattributes[$ExtraTable]['perms'][$key])
							);
				}
			}


			$arrayfieldsFactory = array (
				'nb'=>array('label'=>$langs->trans("QtyNeed"), 'checked'=>1),
				'realstock'=>array('label'=>$langs->trans("RealStock"), 'checked'=>1),
				'qtyorder'=>array('label'=>$langs->trans("QtyOrder"), 'checked'=>0),
				'unitpmp'=>array('label'=>$langs->trans("UnitPmp"), 'checked'=>1),
				'costpmpht'=>array('label'=>$langs->trans("CostPmpHT"), 'checked'=>0),

				'unitpriceht'=>array('label'=>$langs->trans("FactoryUnitPriceHT"), 'checked'=>1),
				'sellingpriceht'=>array('label'=>$langs->trans("FactorySellingPriceHT"), 'checked'=>1),

				'profitamount'=>array('label'=>$langs->trans("ProfitAmount"), 'checked'=>0),
				'weight'=>array('label'=>$langs->trans("Weight"), 'checked'=>1),
				'ordercomponent'=>array('label'=>$langs->trans("Position"), 'checked'=>0),
			);

			// fetch optionals attributes and labels
			$ExtrafieldTable="product_factory";
			$extrafields->fetch_name_optionals_label($ExtrafieldTable);
			$search_array_options = $extrafields->getOptionalsFromPost($ExtrafieldTable, '', 'search_');
			$extrattributes=$extrafields->attributes;
			if (!empty($extrattributes[$ExtrafieldTable]['label']) 
				&& is_array($extrattributes[$ExtrafieldTable]['label']) 
				&& count($extrattributes[$ExtrafieldTable]['label']) > 0) {
				foreach ($extrattributes[$ExtrafieldTable]['label'] as $key => $val) {
					if (!empty($extrattributes[$ExtrafieldTable]['list'][$key]))
						$arrayfieldsFactory["ef.".$key] = array(
								'label'=>$extrattributes[$ExtrafieldTable]['label'][$key], 
								'checked'=>(($extrattributes[$ExtrafieldTable]['list'][$key] < 0) ? 0 : 1), 
								'position'=>$extrattributes[$ExtrafieldTable]['pos'][$key], 
								'enabled'=>(abs($extrattributes[$ExtrafieldTable]['list'][$key]) != 3 && $extrattributes[$ExtrafieldTable]['perms'][$key])
							);
				}
			}

			$arrayfields=array_merge($arrayfieldsProduct, $arrayfieldsFactory);

			include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
			if ($action=='list')
				$action="";
			$varpage =  $_SERVER["PHP_SELF"] ;
			$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields

			// List of subproducts
			if (count($prods_arbo) > 0) {

				$compositionpresente=1;
				print '<b>'.$langs->trans("FactoryTableInfo").'</b>';
				print '<form method="POST" name="listcomponent" action="'.$_SERVER['PHP_SELF'].'">';
				//if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
				print '<input type="hidden" name="action" value="">';
				print '<input type="hidden" name="id" value="'.$id.'">';
				print '<input type="hidden" name="lineid" value="'.$lineid.'">';
				//print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

				print '<div class="div-table-responsive">';
				print '<table class="tagtable liste">'."\n";
				$nbcol=1;
				$param="&id=".$id;
				print '<tr class="liste_titre">';
				print '<td width=16px></td>';

				if (!empty($arrayfields['ref']['checked'])) {
					print_liste_field_titre($arrayfields['ref']['label'], $_SERVER["PHP_SELF"], 'ref', '', $param, '', $sortfield, $sortorder);
					$nbcol++;
				}
				if (!empty($arrayfields['label']['checked'])) {
					print_liste_field_titre($arrayfields['label']['label'], $_SERVER["PHP_SELF"], 'label', '', $param, '', $sortfield, $sortorder);
					$nbcol++;
				}

				// Extra fields
				$extrafieldsobjectkey='product';
				$extrafieldsobjectprefix="efp.";
				$disablesortlink=1;
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
				// pour gérer le nomnbre de colonne en totalisation 
				if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])
					&& is_array($extrafields->attributes[$extrafieldsobjectkey]['label'])) {
					foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $val) {
						if (!empty($arrayfields[$extrafieldsobjectprefix.$key]['checked']))
							$nbcol++;
					}
				}

				if (!empty($arrayfields['nb']['checked'])) {
					print '<td align=right>'.$arrayfields['nb']['label'].'</td>';
					$nbcol++;
				}
				if (!empty($arrayfields['realstock']['checked'])) {
					print '<td align=right>'.$arrayfields['realstock']['label'].'</td>';
					$nbcol++;
				}
				if (!empty($arrayfields['qtyorder']['checked'])) {
					print '<td align=right>'.$arrayfields['qtyorder']['label'].'</td>';
					$nbcol++;
				}
				if (!empty($arrayfields['unitpmp']['checked'])) {
					print '<td align=right>'.$arrayfields['unitpmp']['label'].'</td>';
					$nbcol++;
				}
				if (!empty($arrayfields['costpmpht']['checked'])) {
					print '<td align=right>'.$arrayfields['costpmpht']['label'].'</td>';
				}
				if (!empty($arrayfields['unitpriceht']['checked'])) {
					print '<td align=right>'.$arrayfields['unitpriceht']['label'].'</td>';
				}
				if (!empty($arrayfields['sellingpriceht']['checked'])) {
					print '<td align=right>'.$arrayfields['sellingpriceht']['label'].'</td>';
				}
				if (!empty($arrayfields['profitamount']['checked'])) {
					print '<td align=right>'.$arrayfields['profitamount']['label'].'</td>';
				}
				if (!empty($arrayfields['weight']['checked'])) {
					print '<td align=right>'.$arrayfields['weight']['label'].'</td>';
				}

				if (!empty($arrayfields['ordercomponent']['checked'])) {
					print_liste_field_titre($arrayfields['ordercomponent']['label'], $_SERVER["PHP_SELF"], 'ordercomponent', '', $param, '', $sortfield, $sortorder);
				}

				// Extra fields
				$extrafieldsobjectkey=$ExtrafieldTable;
				$extrafieldsobjectprefix="ef.";
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
				$nbcolExtra=1;
				// pour gérer le nomnbre de colonne en totalisation 
				if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])
					&& is_array($extrafields->attributes[$extrafieldsobjectkey]['label'])) {
					foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $val) {
						if (!empty($arrayfields[$extrafieldsobjectprefix.$key]['checked']))
							$nbcolExtra++;
					}
				}

				print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
				print "</tr>\n";

				$mntTot=0;
				$pmpTot=0;
				$TotWeight=0;
				
				$i=0;

				foreach ($prods_arbo as $key => $prodValue) {
					$productstatic->fetch($prodValue['id']);
					// verify if product have child then display it after the product name
					$tmpChildArbo=$factory->getChildsArbo($prodValue['id']);
					$nbChildArbo="";
					if (count($tmpChildArbo) > 0) 
						$nbChildArbo=" (".count($tmpChildArbo).")";
	
					print '<tr>';
					print '<td align="left">';
					// on détermine le rowid de la ligne
					$currentLine= $factory->get_lineid($id, $prodValue['id']);

					if ($action=="editline" && $lineid==$currentLine) {
						if ((int)DOL_VERSION > 14) 
							print img_picto($langs->trans('Save'), 'fontawesome_save_far_green_1.5em', $moreatt = 'onclick="document.forms[\'listcomponent\'].action.value=\'updateline\';document.forms[\'listcomponent\'].submit();"');
						else
							print img_picto($langs->trans('Save'), 'save.png', $moreatt = 'onclick="document.forms[\'listcomponent\'].action.value=\'updateline\';document.forms[\'listcomponent\'].submit();"');
					} else {
						print '<a href="'.$_SERVER["PHP_SELF"].'?action=editline&lineid='.$currentLine.'&id='.$id.'">';
						print img_edit($langs->trans('Modify'), 1).'</a>';
					}
					print '</td>';

					if (!empty($arrayfields['ref']['checked'])) {
						print '<td align="left">';
						$productsArray = array();
						if ($action=="editline" && $lineid==$currentLine) {
							if (!empty($conf->global->MAIN_MODULE_BOMGENERATOR)) {
								$langs->load("bomgenerator@bomgenerator");
								$categorie_static = new Categorie($db);
								$productFournisseur = new ProductFournisseur($db);
								// on regarde si il y a des configurations de BOM pour ce produit
								dol_include_once("/bomgenerator/class/bomgenerator.class.php");
								$bomGeneratorStatic = new bomGenerator($db);
								$categorieId=$bomGeneratorStatic->get_categorie($id, $i, 1);
								if ($categorieId >0) {
									$categorie_static->fetch($categorieId);
									$productListArray = $categorie_static->getObjectsInCateg('product',  0, 0, 0, 'ref', 'ASC');
									foreach ($productListArray as $keyproduct => $valueproduct) {
										$unit_cost = price2num((!empty($valueproduct->cost_price)) ? $valueproduct->cost_price : $valueproduct->pmp);
										if (empty($unit_cost)) {
											if ($productFournisseur->find_min_price_product_fournisseur($valueproduct->id) > 0) {
												$unit_cost = $productFournisseur->fourn_unitprice;
											}
										}
										$productsArray[$valueproduct->id] = $valueproduct->ref.' - '.$valueproduct->label.' - '.price2num($unit_cost, 'MT');
										//$productsCostArray[$value->id] = $unit_cost;
									};
								}
							}
							if (count($productsArray)>0) {
								print $form->selectarray('fk_product', $productsArray, $prodValue['id'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
							} else {
								// on n'affiche pas les mouvements de stock (on s'en fout ici)
								$tmpstock=$conf->stock->enabled;
								$conf->stock->enabled=null;
								print $form->select_produits($prodValue['id'],'fk_product');
								$conf->stock->enabled=$tmpstock;
							}
						} else {
							print $factory->getNomUrlFactory($prodValue['id'], 1, 'index').$nbChildArbo;
							print $factory->PopupProduct($prodValue['id']);
						}
						print '</td>';
					}
					
					if (!empty($arrayfields['label']['checked'])) {
						print '<td align="left" title="'.$prodValue['description'].'">';
						print $prodValue['label'].'</td>';
					}

					// Extra fields
					$extrafieldsobjectkey='product';
					$extrafieldsobjectprefix="efp.";
					// on récupère les values des extrafields
					$sql = "SELECT efp.rowid";
					if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])) {
						foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $val) 
							$sql .= ($extrafields->attributes[$extrafieldsobjectkey]['type'][$key] != 'separate' ? ", efp.".$key.' as options_'.$key : '');
					}
					$sql.= " FROM ".MAIN_DB_PREFIX."product_extrafields as efp";
					$sql.= ' WHERE efp.fk_object ='.$prodValue['id'];
					$resql = $db->query($sql);
					if ($resql)
						$obj = $db->fetch_object($resql);
					else
						print "erreur".$sql."<br>";


					include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';


					if (!empty($arrayfields['nb']['checked'])) {
						print '<td nowrap align="right">';
						if ($action=="editline" && $lineid==$currentLine) {
							print '<input type=text size=1 name=qtyplanned value="'.$prodValue['nb'].'">';
							// la gestion de la quantité globale aussi?
							print '<input type=checkbox name=globalqty title="'.$langs->trans("Global").'" value=1 '.($prodValue['globalqty']?' tchecked':"").">";
						} else {
							print $prodValue['nb'];
							if ($prodValue['globalqty'] == 1)
								print "&nbsp;G";
						}
						print '</td>';
					}

					$price=$prodValue['price'];
					$pmp=$prodValue['pmp'];

					if (!empty($arrayfields['realstock']['checked'])) {
						if ($prodValue['type'] != "1") {
							$bAllService=false;
							$productstatic->load_stock();
							print '<td align=right>'.$factory->getUrlStock($prodValue['id'], 1, $productstatic->stock_reel).'</td>';
						} else
							print "<td></td>";
					}

					if (!empty($arrayfields['qtyorder']['checked'])) {
						if ($prodValue['type'] != "1") {
							$nbcmde=0;
							// on regarde si il n'y pas de commande fournisseur en cours
							$sql = 'SELECT DISTINCT sum(cofd.qty) as nbCmdFourn';
							$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cofd";
							$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseur as cof ON cof.rowid = cofd.fk_commande";
							$sql.= " WHERE cof.entity = ".$conf->entity;
							$sql.= " AND cof.fk_statut = 3";
							$sql.= " and cofd.fk_product=".$prodValue['id'];
							//print $sql;
							$resql = $db->query($sql);
							if ($resql) {
								$objp = $db->fetch_object($resql);
								if ($objp->nbCmdFourn)
									$nbcmde=$objp->nbCmdFourn;
							}
							print '<td align=right>'.$nbcmde.'</td>';
						} else	// no stock management for services
							print '<td></td>';
					}

					if (!empty($arrayfields['unitpmp']['checked'])) {
						print '<td align=right>';
						if ($action=="editline" && $lineid==$currentLine) {
							print '<input type=text size=3 name=pmp value="'.price2num($pmp).'">';
						} else
							print price($pmp, 0, '', 1, 2, 2); 
						print '</td>';
					} elseif ($action=="editline" && $lineid==$currentLine)
						print '<input type=hidden name=pmp value="'.price2num($pmp).'">';
					
					if (!empty($arrayfields['costpmpht']['checked'])) 
						print '<td align="right">'.price($pmp*$prodValue['nb']).'</td>'; // display total line

					if (!empty($arrayfields['unitpriceht']['checked'])) {
						print '<td align=right>';
						if ($action=="editline" && $lineid==$currentLine) {
							print '<input type=text size=3 name=price value="'.price2num($price).'">';
						} else
							print price($price, 0, '', 1, 2, 2); 
						print '</td>';
					} elseif ($action=="editline" && $lineid==$currentLine)
						print '<input type=hidden name=price value="'.price2num($price).'">';


					if (!empty($arrayfields['sellingpriceht']['checked'])) {
						print '<td align="right">'.price($price*$prodValue['nb']).'</td>';
					}

					if (!empty($arrayfields['profitamount']['checked'])) 
						print '<td align="right">'.price(($price-$pmp)*$prodValue['nb']).'</td>'; 

					if (!empty($arrayfields['weight']['checked'])) {
						if ($prodValue['type'] != "1") {
							// détermination du poids
							$weightunits= $productstatic->weight_units;

							if ($weightunits < 50) {
								// modification en V11
								if ((int) DOL_VERSION > 10)
									$trueWeightUnit=pow(10, $weightunits);
								else
									$trueWeightUnit=pow(10, $weightunits - 2);
								$productWeight=$product->weight * $prodValue['nb'] * $trueWeightUnit;
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
				
							$productWeight=$productstatic->weight * $prodValue['nb'] * $trueWeightUnit;
							print '<td align="right">'.price($productWeight,2,2).' Kg</td>'; 
		
							$TotWeight += $productWeight;
						}
						else print '<td></td>';
					}

					$mntTot=$mntTot+$price*$prodValue['nb'];
					$pmpTot=$pmpTot+$pmp*$prodValue['nb']; // sub total calculation

					if (!empty($arrayfields['ordercomponent']['checked'])) {
						print '<td align=right>';
						if ($action=="editline" && $lineid==$currentLine) {
							print '<input type=text size=1 name=ordercomponent value="'.$prodValue['ordercomponent'].'">';
						} else
							print $prodValue['ordercomponent'];
						print '</td>';
						$nbcolExtra++;
					} elseif ($action=="editline" && $lineid==$currentLine)
						print '<input type=hidden name=ordercomponent value="'.$prodValue['ordercomponent'].'">';


					// Extra fields
					$extrafieldsobjectkey=$ExtrafieldTable;
					$extrafieldsobjectprefix="ef.";

					// on récupère les values des extrafields
					$sql = "SELECT ef.rowid";
					if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])) {
						foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $val) 
							$sql .= ($extrafields->attributes[$extrafieldsobjectkey]['type'][$key] != 'separate' ? ", ef.".$key.' as options_'.$key : '');
					}
					$sql.= " FROM ".MAIN_DB_PREFIX."product_factory_extrafields as ef";
					$sql.= ' , '.MAIN_DB_PREFIX.'product_factory as pf';
					$sql.= ' WHERE pf.rowid = ef.fk_object';
					$sql.= ' AND fk_product_father = '.$id;
					$sql.= ' AND fk_product_children = '.$prodValue['id'];
					$resql = $db->query($sql);
					if ($resql)
						$obj = $db->fetch_object($resql);
					else
						print "erreur".$sql."<br>";

					include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';


					print '<td></td></tr>';
					$i++;
				}

				print '<tr class="liste_total">';
				print '<td colspan='.$nbcol.' align=right >'.$langs->trans("Total").'</td>';
				if (!empty($arrayfields['costpmpht']['checked'])) 
					print '<td align="right" >'.price($pmpTot).'</td>';
				if (!empty($arrayfields['unitpriceht']['checked'])) 
					print '<td align="right" >'.'</td>';
				if (!empty($arrayfields['sellingpriceht']['checked'])) 
					print '<td align="right" >'.price($mntTot).'</td>';
				if (!empty($arrayfields['profitamount']['checked'])) 
					print '<td align="right" >'.price($mntTot-$pmpTot).'</td>';
				if (!empty($arrayfields['weight']['checked'])) 
					print '<td align="right" >'.price($TotWeight).' Kg</td>';
				print '<td colspan='.$nbcolExtra.'></td>';
				print '</tr>';
				print '</table>';
				print '</form>';

			}

		}
		
		// Number of parent products
		$BomList = array();
		if ((int) DOL_VERSION > 10 && !empty($conf->global->MAIN_MODULE_BOM)) {
			$bom_static = new BOM($db);
			// on  sélectionne  les bom ayant le produit en enfant
			$sql = "SELECT bb.rowid, bb.fk_warehouse FROM ".MAIN_DB_PREFIX."bom_bom as bb";
			$sql.= " INNER JOIN ".MAIN_DB_PREFIX."bom_bomline as bl ON bb.rowid = bl.fk_bom";
			$sql.= " WHERE bl.fk_product = ".$id;
			$resql = $db->query($sql);
			if ($resql) {
				$num = $db->num_rows($resql);
				$i = 0;
				while ($i < $num) {
					$obj = $db->fetch_object($resql);
					$BomList[] = array('id' => $obj->rowid, 'fk_warehouse' => $obj->fk_warehouse);
					$i++;
				}
			}
		}

		print load_fiche_titre($langs->trans("ParentComposedProductsNumber").' : '.(count($prodsfather)+count($BomList)), '', '../img/object_factory.png', 1);

		// si il y a des BOM associé
		if (count($BomList) > 0) {
			$entrepot_static = new Entrepot($db);
			print '<b>'.$langs->trans("BOMListTableInfo").'</b><br>';
			print '<table class="border" width=100%>';
			print '<tr class="liste_titre">';
			print '<td class="liste_titre" width=120px align="left">'.$langs->trans("RefBom").'</td>';
			print '<td class="liste_titre" width=250px align="left">'.$langs->trans("Label").'</td>';
			print '<td class="liste_titre" width=150px align="left">'.$langs->trans("Product").'</td>';
			print '<td class="liste_titre" width=150px align="left">'.$langs->trans("Warehouse").'</td>';
			print '<td class="liste_titre" width=50px align="right">'.$langs->trans("Qty").'</td>';
			print '<td class="liste_titre" width=100px align="right">'.$langs->trans("Duration").'</td>';
			print '<td class="liste_titre" width=100px align="right">'.$langs->trans("Status").'</td>';
			print '</tr>';
			foreach ($BomList as $BomElement) {
				// on affiche les infos du BOM
				$bom_static->fetch($BomElement['id']);
				$productstatic->fetch($bom_static->fk_product);

				$entrepot_static->fetch($BomElement['fk_warehouse']);
				print '<tr>';
				print "<td nowrap>".$bom_static->getNomUrl(1)."</td>";
				print "<td>".$bom_static->label."</td>";
				print "<td>".$productstatic->getNomUrl(1)."</td>";
				print "<td>".$entrepot_static->getNomUrl(1)."</td>";
				print "<td align=right>".$bom_static->qty."</td>";
				print "<td align=right>".$bom_static->duration."</td>";
				print "<td align=right>".$bom_static->getLibStatut(2)."</td>";
				print '</tr>';
			}
			print '</table>';

		}

		// si il y a des produits utilisant ce produit
		if (count($prodsfather) > 0) {
			if (count($BomList) > 0) 
				print "<br>";
			print '<b>'.$langs->trans("FactoryParentTableInfo").'</b>';
			// on ajoute la possibilité de remplacer un produit par un autre
			print '<form action="index.php?id='.$id.'" method="post">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="remplacementproduct">';

			print '<table class="border" width=100%>';
			print '<tr class="liste_titre">';
			print '<td class="liste_titre" width=120px align="left">'.$langs->trans("Ref").'</td>';
			print '<td class="liste_titre" width=250px align="left">'.$langs->trans("Label").'</td>';
			print '<td class="liste_titre" width=100px align="left">'.$langs->trans("QtyNeed").'</td>';
			print '<td class="liste_titre" width=50px align="right">'.$langs->trans("RealStock").'</td>';
			print '<td class="liste_titre" width=100px align="right">'.$langs->trans("UnitPmp").'</td>';
			print '<td class="liste_titre" width=100px align="right">'.$langs->trans("SellingPriceHT").'</td>';
			print '<td class="liste_titre" width=50px align="right">'.$langs->trans("Sel").'</td>';

			print '</tr>';
			foreach ($prodsfather as $value) {
				$productstatic->fetch($value["id"]);

				print '<tr>';
				print '<td>'.$factory->getNomUrlFactory($value['id'], 1, 'index');
				print $factory->PopupProduct($value['id']);
				print '</td>';
				print '<td>'.$value["label"].'</td>';

				print '<td align="right">'.$value['qty'];
				if ($value['globalqty'] == 1)
					print "&nbsp;G";
				print '</td>';
	
				print '<td align=right>';
				if ($value['fk_product_type']==0 && $conf->stock->enabled) {
					$productstatic->load_stock();
					print $productstatic->stock_reel;
				}
				print '</td>';
				print '<td align="right">'.price($productstatic->pmp).'</td>';
				print '<td align="right">'.price($productstatic->price).'</td>';
				print '<td align="right"><input type=checkbox name="productchange[]" value="'.$value["id"].'"></td>';

				print '</tr>';
			}
			print '<tr class="liste_titre"><th align=right colspan=2>';
			print $langs->trans("AlternativeProduct").'&nbsp;:&nbsp;';
			print '</th>';
			print '<td colspan=2>';
			print $form->select_produits($id, 'fk_product', '');
			print '</td>';
			print '<td align=right colspan=2>';
			print '<input type="submit" class="button" value="'.$langs->trans("ChangeProduct").'">';
			print '</td></tr>';
			print '</table>';
	
		}

		if ($action == 'adjustprice') {
			print '<br>';
			print load_fiche_titre($langs->trans("AdjustPrice"), '', '../img/object_factory.png', 1);

			print '<form action="index.php?id='.$id.'" method="post">';
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

			foreach ($prods_arbo as $value) {
				$productstatic->id=$value['id'];
				$productstatic->fetch($value['id']);
				$productstatic->type=$value['type'];

				$var=!$var;
				print "\n<tr ".$bc[$var].">";
				
				print '<td>'.$factory->getNomUrlFactory($value['id'], 1, 'fiche', 24).'</td>';
				$labeltoshow=$productstatic->label;

				print '<td>'.$labeltoshow.'</td>';
				
				if ($factory->is_sousproduit($id, $productstatic->id))
					$qty=$factory->is_sousproduit_qty;
				else
					$qty="X"; // il y a un soucis, voir
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

			print '<br><center>';
			print ' <input type="submit" class="button" value="'.$langs->trans("Update").'">';
			print ' &nbsp; &nbsp;';
			print ' <input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
			print '</center>';
			print '</form>';
		}

		$rowspan=1;
		if (!empty($conf->categorie->enabled)) $rowspan++;
		if ($action == 'edit' || $action == 'search' || $action == 're-edit' ) {

			print load_fiche_titre($langs->trans("ProductToAddSearch"), '', '../img/object_factory.png', 1);

			print '<form action="index.php?id='.$id.'" method="post">';
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
			print '<td rowspan='.$rowspan.'>';
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
				if ($resqlsearch) {
					$num = $db->num_rows($resqlsearch);
					$i=0;
					$limit = 200;
					if ($num > $limit) print '<b>'.$langs->trans("TooMuchProductFind").'</b>';
					print '<br>';
					print '<form action="index.php?id='.$id.'" method="post">';
					print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
					print '<input type="hidden" name="action" value="add_prod">';
					print '<input type="hidden" name="id" value="'.$id.'">';
					print '<table class="nobordernopadding" width="100%">';
					print '<tr class="liste_titre">';
					print '<th class="liste_titre">'.$langs->trans("Ref").'</th>';
					print '<th class="liste_titre">'.$langs->trans("Label").'</th>';
					print '<th class="liste_titre" align="right">'.$langs->trans("BuyPrice").'</th>';
					print '<th class="liste_titre" align="right">'.$langs->trans("SellPrice").'</th>';
					if (!empty($conf->stock->enabled))
						print '<th class="liste_titre" align="right">'.$langs->trans("Stock").'</th>'; 
					print '<th class="liste_titre" align="right">'.$langs->trans("QtyOrder").'</th>';
					print '<th class="liste_titre" align="right">'.$langs->trans("Quantity").'</th>';
					print '<th class="liste_titre" align="center">'.$langs->trans("AddDel").'</th>';
					print '<th class="liste_titre" align="right">'.$langs->trans("Global").'</th>';
					print '</tr>';

	
					if ($num == 0) print '<tr><td colspan="4">'.$langs->trans("NoMatchFound").'</td></tr>';

	
					while ($i < min($num, $limit)) {
						$objp = $db->fetch_object($resqlsearch);
						print "\n<tr>";
						$productstatic->id=$objp->rowid;
						$productstatic->ref=$objp->ref;
						$productstatic->libelle=$objp->label;
						$productstatic->label=$objp->label;
						$productstatic->type=$objp->type;

						print '<td><label id=line'.$objp->rowid.'>';
						print $factory->getNomUrlFactory($objp->rowid, 1, 'index', 24, $productstatic->ref);
						print $factory->PopupProduct($objp->rowid, $i);
						print '</td>';

						$labeltoshow=$objp->label;
						//if ($conf->global->MAIN_MULTILANGS && $objp->labelm) $labeltoshow=$objp->labelm;

						print '<td>';
						print "<a href='#line".$objp->rowid."' onclick=\"$('.detailligne".$i."').toggle();\" >";
						print img_picto("", "opendown@factory")."</a>&nbsp;";
						print $labeltoshow.'</td>';
						if ($factory->is_sousproduit($id, $objp->rowid)) {
							$addchecked = ' checked="checked" ';
							$qty=$factory->is_sousproduit_qty;
							$descComposant=$factory->is_sousproduit_description;
							$ordercomponent=$factory->is_sousproduit_ordercomponent;
							$qtyglobal=$factory->is_sousproduit_qtyglobal;
						} else {
							$addchecked = '';
							$descComposant = $objp->addinforecup;
							$qty="1";
							$ordercomponent="0"; // par défaut pas d'ordre
							$qtyglobal=0;
						}
						print '<td align="right">'.price($objp->pmp).'</td>';
						print '<td align="right">'.price($objp->price).'</td>';

						if (!empty($conf->stock->enabled)) {
							$productstatic->load_stock();
							print '<td align=right>'.$productstatic->stock_reel.'</td>';
						} else
							print '<td ></td>';

						if ($objp->type==0) { 	// if product
							$nbcmde=0;
							// on regarde si il n'y pas de commande fournisseur en cours
							$sql = 'SELECT DISTINCT sum(cofd.qty) as nbCmdFourn';
							$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cofd";
							$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseur as cof ON cof.rowid = cofd.fk_commande";
							$sql.= " WHERE cof.entity = ".$conf->entity;
							$sql.= " AND cof.fk_statut = 3";
							$sql.= " and cofd.fk_product=".$objp->rowid;
							//print $sql;
							$resql = $db->query($sql);
							if ($resql) {
								$objpcmd = $db->fetch_object($resql);
								if ($objpcmd->nbCmdFourn)
									$nbcmde=$objpcmd->nbCmdFourn;
							}
							print '<td align=right>'.$nbcmde.'</td>';
						}
						else	// no stock management for services
							print '<td></td>';

						print '<td align="right" >';
						print '<input type="text" size="3" name="prod_qty_'.$i.'" value="'.$qty.'">';
						print '</td><td align="center">';
						print '<input type="checkbox" '.$addchecked.' name="prod_id_chk'.$i.'" value="'.$objp->rowid.'">';
						print '<input type="hidden" name="prod_id_'.$i.'" value="'.$objp->rowid.'">';
						print '</td>';
						print '<td align=right>';

						print $form->selectyesno('prod_id_globalchk'.$i, $qtyglobal, 1);
						print '</td></tr>';

						print "<tr style='display:none' class='detailligne".$i."'>";
						print '<td ></td>';
						print '<td valign=top align=left >'.$langs->trans("Position").' : ';
						print '<input type="text" size="2" name="prod_order_'.$i.'" value="'.$ordercomponent.'">';
						print '</td>';
						print '<td valign=top align=left colspan=2>'.$langs->trans("InfoAnnexes").' <br>';
						print '<textarea name="descComposant'.$i.'" wrap="soft" cols="70" rows="'.ROWS_3.'">';
						print $descComposant.'</textarea>';
						print '</td>';
						// ici les extrafields en saisie
						print '<td valign=top align=left colspan=5>';
						//extrafields_edit.tpl
						$ExtrafieldTable="product_factory";
						if (!empty($extrafields->attributes[$ExtrafieldTable]['label']) 
							&& is_array($extrafields->attributes[$ExtrafieldTable]['label']) 
							&& count($extrafields->attributes[$ExtrafieldTable]['label']) > 0) {
							print '<table>';
							foreach ($extrafields->attributes[$ExtrafieldTable]['label'] as $key => $val) {
								print '<tr><td>'.$extrafields->attributes[$ExtrafieldTable]['label'][$key];
								print ' </td><td>: ';
								// on récupère les values des extrafields pour ce champs
								$sql = "SELECT ef.* FROM ".MAIN_DB_PREFIX."product_factory_extrafields as ef";
								$sql.= ' , '.MAIN_DB_PREFIX.'product_factory as pf';
								$sql.= ' WHERE pf.rowid = ef.fk_object';
								$sql.= ' AND fk_product_father = '.$id;
								$sql.= ' AND fk_product_children = '.$objp->rowid;
								$resql = $db->query($sql);
								if ($resql)
									$obj = $db->fetch_object($resql);
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
					print '</table>';
					// si plus de 200 on limite n'affiche que 200 enregs
					print '<input type="hidden" name="max_prod" value="'.$i.'">';
		
					if ($num > 0) {
						print '<br><center>';
						print '<input type="submit" class="butAction" value="'.$langs->trans("Add").'/'.$langs->trans("Update").'">';
						print ' &nbsp; &nbsp; <input type="submit" name="cancel" class="butAction" value="'.$langs->trans("Cancel").'">';
						print '</center>';
					}
					print '</form>';

				} else
					dol_print_error($db);
			}
		}
	}
}

/* Barre d'action			*/
print '<div class="tabsAction">';
$object->fetch($id, $ref);

if ($action == '' && $bproduit && $user->rights->factory->creer) {
	//Le stock doit être actif et le produit doit être identifié manufacturé
	if (!empty($conf->stock->enabled) && $object->finished == '1') {
		print '<a class="butAction" href="index.php?action=edit&token='.newToken().'&id='.$productid.'">';
		print $langs->trans("EditComponent").'</a>';
		print '<a class="butAction" href="index.php?action=importexport&token='.newToken().'&id='.$productid.'">';
		print $langs->trans("ImportExportComposition").'</a>';

		if ($compositionpresente) {
			// gestion des prix
			print '<a class="butAction" href="index.php?action=getdefaultprice&token='.newToken().'&id='.$productid.'">';
			print $langs->trans("GetDefaultPrice").'</a>';
			print '<a class="butAction" href="index.php?action=adjustprice&token='.newToken().'&id='.$productid.'">';
			print $langs->trans("AdjustPrice").'</a>';
		} else {
			//uniquement si les produits virtuels sont actifs
			if (! empty($conf->global->PRODUIT_SOUSPRODUITS)) {
				print '<a class="butAction" href="index.php?action=getfromvirtual&token='.newToken().'&id='.$productid.'">';
				print $langs->trans("GetFromVirtual").'</a>';
			}
		}
		if (!empty($conf->variants->enabled)) {
			// si il s'agit d'un produit variant
			require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination.class.php';
			$prodcomb = new ProductCombination($db);
			$product_pere = $prodcomb->fetchByFkProductChild($object->id, 0);
			if ($product_pere > 0) {
				print '<a class="butAction" href="index.php?action=getfromvariant&token='.newToken().'&id='.$productid.'&product_pere='.$product_pere.'">';
				print $langs->trans("GetFromVariant").'</a>';
			}
		}
	} else
		print $langs->trans("NeedFinishedProductAndStockEnabled");
}

print '</div>';
llxFooter();
$db->close();