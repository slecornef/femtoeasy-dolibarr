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
 *  \file	   htdocs/factory/product/fiche.php
 *  \ingroup	product
 *  \brief	  Page de création des Ordres de fabrication sur la fiche produit
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
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

if (! empty($conf->global->FACTORY_ADDON) 
		&& is_readable(dol_buildpath("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php")))
	dol_include_once("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php");

$langs->load("bills");
$langs->load("products");
$langs->load("factory@factory");

$factoryid=GETPOST('factoryid', 'int');
$id		= GETPOST('id', 'int');
$bomid 	= GETPOST('bomid', 'int');

$ref	= GETPOST('ref', 'alpha');
$action	= GETPOST('action', 'alpha');
if (GETPOST("verifyof"))
	$action = "verifyof";
if (GETPOST("ofgenerator"))
	$action = 'ofgenerator';
if (GETPOST("changeselectedproduct"))
	$action ="changeselectedproduct";

$confirm= GETPOST('confirm', 'alpha');
$cancel	= GETPOST('cancel', 'alpha');
$key	= GETPOST('key');
$parent = GETPOST('parent');

// Security check
if (! empty($user->socid)) $socid=$user->socid;
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
$result=restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);

$mesg = '';

$object = new Product($db);
$factory = new Factory($db);
$product = new Product($db);
// fetch optionals attributes and labels
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label("factory");

$productid=0;
if ($id || $ref) {
	//si on passe par un bom
	if ($id==-1 && $bomid> 0) {
		require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
		$bom_static = new BOM($db);
		$bom_static->fetch($bomid);
		$id = $bom_static->fk_product;
		$factory->fk_bom = $bomid;
	}
	$result = $object->fetch($id, $ref);
	$productid=$object->id;
	$id=$object->id;
	$factory->id =$id;
}

/*
 * Actions
 */
if ($cancel == $langs->trans("Cancel"))
	$action = '';

// build product on each store
if ($action == 'createof' && GETPOST("createofrun")) {
	// on récupère les valeurs saisies
	$factory->fk_entrepot=GETPOST("entrepotid");
	$factory->qty_planned=GETPOST("nbToBuild");
	$factory->date_start_planned=dol_mktime(
					GETPOST('plannedstarthour', 'int'), GETPOST('plannedstartmin', 'int'), 0,
					GETPOST('plannedstartmonth', 'int'), GETPOST('plannedstartday', 'int'), GETPOST('plannedstartyear', 'int')
	);	
	$factory->date_end_planned=dol_mktime(
					GETPOST('plannedendhour', 'int'), GETPOST('plannedendmin', 'int'), 0,
					GETPOST('plannedendmonth', 'int'), GETPOST('plannedendday', 'int'), GETPOST('plannedendyear', 'int')
	);
	// on vérifie que la durée est saisie
	$factory->duration_planned=(GETPOST("workloadhour")?GETPOST("workloadhour", "int"):0)*3600+(GETPOST("workloadmin")?GETPOST("workloadmin", "int"):0)*60;
	$factory->description=GETPOST("description");
	$factory->fk_factory_parent=0;
	// Fill array 'array_options' with data from add form
	$ret = $extrafields->setOptionalsFromPost($extralabels, $factory);
	if ($ret < 0) $error++;
	
	if (! $error) {
		$newref=$factory->createof();
		// Little message to inform of the number of builded product
		$mesg='<div class="ok">'.$newref.' '.$langs->trans("FactoryOrderSaved").'</div>';
		//$action="";
		// on affiche la liste des of en cours pour le produit 
		Header("Location: list.php?fk_status=1&id=".$id);	
	} else {
		// Required extrafield left blank, error message already defined by setOptionalsFromPost()
		$action = 'verifyof';
	}
}

if ($action == 'changeselectedproduct') {

	dol_include_once("/bomgenerator/class/bomgenerator.class.php");
	$factorygenerator = new factorygenerator($db);

	// on mémorise la constitution de l'of ??
	$prodsfather = $factory->getFather(); //Parent Products
	$factory->get_sousproduits_arbo();
	// Number of subproducts
	$prods_arbo = $factory->get_arbo_each_prod();
	
	// on efface la précédente mise en mémoire
	$factorygenerator->deletelines($id);

	foreach ($prods_arbo as $key => $value) {
		// on récupére l'id du produit si saisie
		$newProductId = GETPOST('product_'.$key,'int');
		if ($newProductId > 0 && $newProductId != $value['id']) {
			$factorygenerator->set_productchanged($id, $key, $newProductId);
		}
	}	
	$action = "verifyof";
}


/*
 * View
 */


$productstatic = new Product($db);
$form = new Form($db);

llxHeader("", "", $langs->trans("CardProduct".$product->type));

$head=product_prepare_head($object, $user);
$titre=$langs->trans("CardProduct".$object->type);
$picto=('product');
dol_fiche_head($head, 'factory', $titre, -1, $picto);
$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

if ($id || $ref) {
	$bproduit = ($object->isproduct()); 
	if ($result) {
		dol_banner_tab($object, 'ref', $linkback, ($user->socid?0:1), 'ref');

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

		// on indique la nature du produit
		print '<tr><td>'.$form->textwithpicto($langs->trans("NatureOfProductShort"), $langs->trans("NatureOfProductDesc")).'</td>';
		print '<td>'.$object->getLibFinished().'</td></tr>';
		

		print '<tr><td>'.$langs->trans("PhysicalStock").'</td>';
		print '<td>'.$object->stock_reel.'</td></tr>';
		print '</table>';
		print '</div>';

		print '</div></div>';
		print '<div style="clear:both"></div>';

		dol_fiche_end();

		// indique si on a déjà une composition de présente ou pas
		$compositionpresente=0;
		
		// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
		$hookmanager->initHooks(array('prodfactorycard', 'globalcard'));

		$parameters = array('product' => $id);
		// Note that $action and $object may have been modified by some hooks
		$reshook = $hookmanager->executeHooks('doActions', $parameters, $factory, $action); 
		if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');


		$head=factory_product_prepare_head($object, $user);
		$titre=$langs->trans("Factory");
		$picto="factory@factory";
		dol_fiche_head($head, 'neworderbuild', $titre, -1, $picto);

		if ($factory->fk_bom > 0) {
			// on récupère la composition du BOM
			require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
			$bom_static = new BOM($db);
			$product_static = new Product($db);
			$bom_static->fetch($factory->fk_bom);
			foreach ($bom_static->lines as $line) {
				$product_static->fetch($line->fk_product);
				$product_static->load_stock();
				$stockReal=0;
				if (!empty($product_static->stock_warehouse))
					$stockReal= $product_static->stock_warehouse[1]->real;

				$prods_arbo[$line->fk_product]['id']= $line->fk_product;
				$prods_arbo[$line->fk_product]['label']= $product_static->label;
				$prods_arbo[$line->fk_product]['pmp']= $product_static->pmp;
				$prods_arbo[$line->fk_product]['price']= $product_static->price;
				$prods_arbo[$line->fk_product]['nb'] = $line->qty;
				$prods_arbo[$line->fk_product]['nb_total'] = $factory->qty * $line->qty;
				$prods_arbo[$line->fk_product]['stock']= $stockReal;
				$prods_arbo[$line->fk_product]['stock_alert']= $product->seuil_stock_alerte;
				$prods_arbo[$line->fk_product]['type']= $product->type;
				$prods_arbo[$line->fk_product]['globalqty']= $line->qty_frozen;
				$prods_arbo[$line->fk_product]['description']= $line->description;
				$prods_arbo[$line->fk_product]['ordercomponent']= $line->position;

			}
			
		} else  {
			$prodsfather = $factory->getFather(); //Parent Products
			$factory->get_sousproduits_arbo();
			// Number of subproducts
			$prods_arbo = $factory->get_arbo_each_prod();
		}

		if ($action != "verifyof" && $action != "ofgenerator") {
			// something wrong in recurs, change id of object
			$factory->id = $id;
			print load_fiche_titre($langs->trans("FactorisedProductsNumber").' : '.count($prods_arbo), '', '');
			
			// List of subproducts
			if (count($prods_arbo) > 0) {
				$compositionpresente=1;
				print '<b>'.$langs->trans("FactoryTableInfo").'</b><BR>';
				print '<table class="border" >';
				print '<tr class="liste_titre">';
				print '<th class="liste_titre" width=100px align="left">'.$langs->trans("Ref").'</th>';
				print '<th class="liste_titre" width=200px align="left">'.$langs->trans("Label").'</th>';
				print '<th class="liste_titre" width=50px align="center">'.$langs->trans("QtyNeed").'</th>';
				// on affiche la colonne stock même si cette fonction n'est pas active
				print '<th class="liste_titre" width=50px align="center">'.$langs->trans("RealStock").'</th>'; 
				print '<th class="liste_titre" width=100px align="center">'.$langs->trans("QtyOrder").'</th>';
				if (!empty($conf->stock->enabled)) { 	// we display swap titles
					print '<th class="liste_titre" width=100px align="right">'.$langs->trans("UnitPmp").'</th>';
					print '<th class="liste_titre" width=100px align="right">'.$langs->trans("CostPmpHT").'</th>';
				} else { 	// we display price as latest purchasing unit price title
					print '<th class="liste_titre" width=100px align="right">'.$langs->trans("UnitHA").'</th>';
					print '<th class="liste_titre" width=100px align="right">'.$langs->trans("CostHA").'</th>';
				}
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("FactoryUnitPriceHT").'</th>';
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("FactorySellingPriceHT").'</th>';
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("ProfitAmount").'</th>';

				print '</tr>';
				$mntTot=0;
				$pmpTot=0;

				foreach ($prods_arbo as $value) {
					// verify if product have child then display it after the product name
					$tmpChildArbo=$factory->getChildsArbo($value['id']);
					$nbChildArbo="";
					if (count($tmpChildArbo) > 0) $nbChildArbo=" (".count($tmpChildArbo).")";

					print '<tr>';
					print '<td align="left">'.$factory->getNomUrlFactory($value['id'], 1, 'fiche').$nbChildArbo;
					print $factory->PopupProduct($value['id']);
					print '</td>';

					print '<td align="left" title="'.$value['description'].'">';
					print $value['label'].'</td>';
					print '<td align="center">'.$value['nb'];
					if ($value['globalqty'] == 1)
						print "&nbsp;G";
					print '</td>';
					$price=$value['price'];
					$pmp=$value['pmp'];

					if (!empty($conf->stock->enabled)) {
						// we store vwap in variable pmp and display stock
						$productstatic->fetch($value['id']);

						if ($value['type']==0 || empty($value['type'])) { // if product
							print '<td align=center>' . $factory->getUrlStock($value['id'], 1, $productstatic->stock_reel) . '</td>';

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
								$objp = $db->fetch_object($resql);
								if ($objp->nbCmdFourn)
									$nbcmde=$objp->nbCmdFourn;
							}
							print '<td align=right>'.$nbcmde.'</td>';
						} else // no stock management for services
							print '<td></td><td></td>';
					}
					// display else vwap or else latest purchasing price
					print '<td align="right">'.price($value['pmp']).'</td>'; 
					print '<td align="right">'.price($value['pmp']*$value['nb']).'</td>'; // display total line

					print '<td align="right">'.price($value['price']).'</td>';
					print '<td align="right">'.price($value['price']*$value['nb']).'</td>';
					print '<td align="right">'.price(($value['price']-$value['pmp'])*$value['nb']).'</td>'; 
					
					$mntTot=$mntTot+$value['price']*$value['nb'];
					$pmpTot=$pmpTot+$value['pmp']*$value['nb']; // sub total calculation
					
					print '</tr>';

					//var_dump($value);
					//print '<pre>'.$productstatic->ref.'</pre>';
					//print $productstatic->getNomUrl(1).'<br>';
					//print $value[0];	// This contains a tr line.

				}
				print '<tr class="liste_total">';
				print '<td colspan=6 align=right >'.$langs->trans("Total").'</td>';
				print '<td align="right" >'.price($pmpTot).'</td>';
				print '<td ></td>';
				print '<td align="right" >'.price($mntTot).'</td>';
				print '<td align="right" >'.price($mntTot-$pmpTot).'</td>';
				print '</tr>';
				print '</table>';
				print '<br>';
			}
		}
		if ($action == 'build' || $action == 'createof' || $action == 'ofgenerator' || $action == "verifyof") {
			// Display the list of store with buildable product 

			print load_fiche_titre($langs->trans("CreateOF"), '', '');
			
			print '<form action="fiche.php?id='.$id.'" method="post">';
			print '<input type="hidden" name="action" value="createof">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<div class="fichecenter">';
			print '<div class="fichehalfleft">';
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield" width="100%">';
			print '<tr><td width=50%>'.$langs->trans("EntrepotStock").'</td><td>';
			$entrepotid = (GETPOST("entrepotid")?GETPOST("entrepotid"):$object->fk_default_warehouse);
			print select_entrepot_list($entrepotid, "entrepotid", 0, 1);
			print '</td></tr>';
			print '<tr><td class="fieldrequired">'.$langs->trans("QtyToBuild").'</td>';
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
			print '</table>';


			print '</div>';
			print '<div class="fichehalfright"><div class="ficheaddleft">';
	
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield" width="100%">';
	

			print '<tr><td width=40%>'.$langs->trans("FactoryDurationPlanned").'</td>';
			print '<td>';
			$workloadhour=(GETPOST("workloadhour")?GETPOST("workloadhour"):0)*3600;
			$workloadhour+=(GETPOST("workloadmin")?GETPOST("workloadmin"):0)*60;
			print $form->select_duration(
							'workload', $workloadhour, 0, 'text'
			);
			print '</td></tr>';
			
			// Other attributes
			if (!empty($extrafields->attributes['factory']['label'])) {
				print $factory->showOptionals($extrafields, 'edit');
			}
			
			print '<tr><td colspan=2 valign="top">'.$langs->trans("Description").'</td></tr>';
			print '<tr><td colspan=2 align=right>';
			$description=GETPOST("description");
			// on récupère le text de l'extrafields si besoin
			if (!empty($conf->global->factory_extrafieldsNameInfo)) {
				$sql = 'SELECT DISTINCT pe.'.$conf->global->factory_extrafieldsNameInfo. ' as addinforecup';
				$sql.= ' FROM '.MAIN_DB_PREFIX.'product_extrafields as pe ';
				$sql.= ' WHERE pe.fk_object =' .$id;
				$resql = $db->query($sql);
				if ($resql) {
					$objp = $db->fetch_object($resql);
					if ($objp->addinforecup)
						$description=$objp->addinforecup;
				}
			}
			
			print '<textarea name="description" wrap="soft" cols="50" rows="'.ROWS_3.'">'.$description.'</textarea>';
			print '</td></tr>';
			print '</table>';
			print '</div></div></div></div>';
			print '<div class="clearboth"></div>';

			print '<div class="tabsAction">';
			if ($action == "build" || $action == "verifyof")
				print '<input type="submit" class="butAction" name="verifyof" value="'.$langs->trans("VerifyQty").'"></td>';
			print '</div >';
			print '<br>';

			// seconde partie du controle
			if ($action == "verifyof" || $action == "ofgenerator") {
				if (!empty($conf->global->MAIN_MODULE_BOMGENERATOR)) {
					$langs->load("bomgenerator@bomgenerator");
					$categorie_static = new Categorie($db);
					$productFournisseur = new ProductFournisseur($db);
					// on regarde si il y a des configurations de BOM pour ce produit
					dol_include_once("/bomgenerator/class/bomgenerator.class.php");
					$bomGeneratorStatic = new bomGenerator($db);
				}
				// on vérifie que la quantité à fabriquer a bien été saisie (valeur obligatoire)
				if (GETPOST("nbToBuild", "int") <> 0 ) {
					// List of subproducts
					if (count($prods_arbo) > 0) {
						$nbtobuild=GETPOST("nbToBuild",'int');
						print '<table class="border tableforfield" width="100%">';
						print '<tr class="liste_titre">';
						print '<td class="liste_titre" width=100px align="left">'.$langs->trans("Ref").'</td>';
						print '<td class="liste_titre" width=200px align="left">'.$langs->trans("Label").'</td>';
						print '<td class="liste_titre" width=100px align="right">'.$langs->trans("QtyNeedOF").'</td>';
						print '<td class="liste_titre" width=100px align="right">'.$langs->trans("QtyOfWarehouse").'</td>'; 
						print '<td class="liste_titre" width=100px align="right">'.$langs->trans("QtyOrder").'</td>';
						print '<td class="liste_titre" width=100px align="right">'.$langs->trans("QtyAlert").'</td>';
						print '</tr>';
						foreach ($prods_arbo as $key => $value) {
							//var_dump($value);
							$productstatic->id=$value['id'];
							$productstatic->fetch($value['id']);
							$productstatic->type=$value['type'];
							// verify if product have child then display it after the product name
							//$tmpChildArbo=$productstatic->getChildsArbo($value['id']);
							$tmpChildArbo=$factory->getChildsArbo($value['id']);
							$nbChildArbo="";
							if (count($tmpChildArbo) > 0) $nbChildArbo=" (".count($tmpChildArbo).")";
			
							$nbChildArbo="";
							if (count($tmpChildArbo) > 0) $nbChildArbo=" (".count($tmpChildArbo).")";
			
							print '<tr>';
							print '<td align="left">'.$factory->getNomUrlFactory($value['id'], 1, 'fiche').$nbChildArbo;
							print $factory->PopupProduct($value['id'], $i);
							print '</td>';
							if($action == "ofgenerator") {
								$productsArray=Array();
								print '<td align="left">';
								$categorieId=$bomGeneratorStatic->get_categorie($id, $key, 1);
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
									if (count($productsArray)>0)
										print $form->selectarray('product_'.$key, $productsArray, $value['id'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');

								} else 
									print $productstatic->label;
								print '</td>';
							} else
								print '<td align="left">'.$productstatic->label.'</td>';
							
							if ($value['globalqty'] == 0)
								print '<td align="right">'.$value['nb']*$nbtobuild.'</td>';
							else
								print '<td align="right">'.$value['nb'].'</td>';
							// uniquement pour les produits pas pour les services
							if ($value['type']!=1) { 	// if product
								$productstatic->load_stock();
								$stockEntrepot =0;
								if (!empty($productstatic->stock_warehouse))
									$stockEntrepot = $productstatic->stock_warehouse[GETPOST("entrepotid", "int")]->real;
								print '<td align=right>'.$stockEntrepot.'</td>';
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
									$objp = $db->fetch_object($resql);
									if ($objp->nbCmdFourn)
										$nbcmde=$objp->nbCmdFourn;
								}
								print '<td align=right>'.$nbcmde.'</td>';
								print '<td align=right>';
								// pour gêrer les niveaux d'alertes
								if ($value['globalqty'] == 0)
									$qterestante= $stockEntrepot - $value['nb']*$nbtobuild;
								else
									$qterestante= $stockEntrepot - $value['nb'];

								if ($qterestante < 0) {
									// on pondère selon la quantité en cours
									$qtyRestanteGlobal = $product->stock_reel + $nbcmde;
									if ($qtyRestanteGlobal<0) {
										print "<font color='red'><b>KO (".$qterestante.")</b></font>";
									} else {
										print "<font color='orange'><b>KO (".$qterestante.")</b></font>";
									}		
									if (count($tmpChildArbo) > 0) {
										// on affiche les composants en ligne suivante
										print "(".count($tmpChildArbo).")";
									}
								}
								else // là on est OK
									print '<font color="green"><b>'."OK".'</b></font>';
								print '</td>';
							} else {
								// no stock management for services, all is OK
								print '<td  colspan=3 align=center>'.$langs->trans("Service").'</td>';
							}
							print '</tr>';
						}
						print '</table>';
					}
				}
				else
					$mesg='<div class="error">'.$langs->trans("QuantityToBuildNotNull").'</div>';
			}

			if (GETPOST("nbToBuild") <> 0) {
				print '<div class="tabsAction">';
				if ($action == "verifyof" )
					print '<input type="submit" class="butAction" name="createofrun" value="'.$langs->trans("LaunchOF").'">';
				if (!empty($conf->global->MAIN_MODULE_BOMGENERATOR)) {
					// on regarde si il y a des configurations de BOM pour ce produit
					dol_include_once("/bomgenerator/class/bomgenerator.class.php");
					$bomGeneratorStatic = new bomGenerator($db);
					if( $bomGeneratorStatic->IsBomGenerator($productid, 1) > 0) {
						if($action == "ofgenerator")
							print '<input type="submit" class="butAction" name="changeselectedproduct" value="'.$langs->trans("ChangeSelectedProduct").'">';
						else
							print '<input type="submit" class="butAction" name="ofgenerator" value="'.$langs->trans("ChangeOFComposition").'">';
					}

				}
				print '</div>';
			}
			print '</form>';
		}
	}
}

dol_htmloutput_mesg($mesg);

if (empty($conf->global->FACTORY_ADDON))
	print $langs->trans("NeedToDefineFactorySettingFirst");
else {

	/* Barre d'action				*/
	print '<div class="tabsAction">';
	
	$parameters = array();
	// Note that $action and $object may have been
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $factory, $action); 

	$object->fetch($id, $ref);
	if ($action == '' && $bproduit) {
		if ($user->rights->factory->creer) {
			//Le stock doit être actif et le produit de nature manufacturé
			if (!empty($conf->stock->enabled) && $object->finished == '1')
				if ($compositionpresente) {
					print '<a class="butAction" href="fiche.php?action=build&id='.$productid.'">';
					print $langs->trans("CreateOF").'</a>';
				} else
					print $langs->trans("NeedNotBuyProductAndStockEnabled");
		}
	}
	print '</div>';
}

llxFooter();
$db->close();