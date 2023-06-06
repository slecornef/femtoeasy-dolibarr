<?php
/* Copyright (C) 2001-2007	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2011		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2019-2022	Charlene BENKE			<charlene@patas-monkey.com>
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";


dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

$langs->load("bills");
$langs->load("products");

$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$keysearch=GETPOST('keysearch');
$parent=GETPOST('parent');

// Security check
if (! empty($user->socid)) $socid=$user->socid;
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
$result=restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype, $objcanvas);

$mesg = '';

$object = new Product($db);
$factory = new Factory($db);
$productid=0;
if ($id || $ref) {
	$result = $object->fetch($id, $ref);
	$productid=$object->id;
	$id=$object->id;
	$factory->id =$id;
}

// on récupère le coef de marge par défaut.
if (GETPOST("coefmargin"))
	$computepricedefaultcoef = GETPOST("coefmargin");
else
	$computepricedefaultcoef = !empty($conf->global->factory_computepricedefaultcoef)?$conf->global->factory_computepricedefaultcoef:1;


/*
 * Actions
 */




if ($action == 'getdefaultprice') {	
	$factory->getdefaultprice();
	$action="";
}

if ($action == 'updateprice') {
	// on modifie les prix 
	$prodsfather = $factory->getFather($productid); //Parent Products
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

if ($action == 'computeprice') {

	$coefmargin = GETPOST("coefmargin");
	$mntTot = GETPOST("mnttot");
	$pmpTot = GETPOST("pmptot");

	// on récupère les anciennes infos de prix
	// récupération des produits à mettre à jour
	$sql = "SELECT p.pmp, p.rowid as fk_product";
	$sql.= " , '1' as price_level, p.price, p.price_ttc, p.price_min, p.price_min_ttc";
	$sql.= " , p.tva_tx, p.recuperableonly, p.price_base_type";
	$sql.= " , '0' as price_by_qty, p.datec as lastdate, p.tosell";
	$sql.= " , p.fk_price_expression, p.localtax1_tx, p.localtax1_type";
	$sql.= " , p.localtax2_tx, p.localtax2_type, p.cost_price"; 
	$sql.= " FROM ".MAIN_DB_PREFIX."product as p";		
	$sql.= " WHERE p.entity IN (".getEntity('product', 1).")";
	$sql.= " AND p.rowid = ".$productid;

	$resqlprod=$db->query($sql);

	$obj = $db->fetch_object($resqlprod);

	// on effectue un calcul par le prix de vente ou d'achat des composants
	if ($conf->global->factory_origincomputeprice == "frombuyprice")
		$newprice = $mntTot * ($coefmargin / 100);
	else
		$newprice = $pmpTot * ($coefmargin / 100);

	$tmparray=calcul_price_total(
					1, $newprice, 0, $obj->tva_tx, 0, 0, 0, 
					$obj->price_base_type, 0, 0, 0
	);
	$newprice = $tmparray[0];
	$price_ttc = $tmparray[2];

	$price_min= 0;
	$price_min_ttc = 0;
	// Si le produit a un prix minimum renseign�
	if ($obj->price_min >0 ) {
		$price_min = $newprice - ($obj->price - $obj->price_min);
		$tmparray=calcul_price_total(
						1, $price_min, 0, $obj->tva_tx, 0, 0, 0, 
						$obj->price_base_type, 0, 0, 0
		);
		if ($tmparray[0] > 0) {
			$price_min = $tmparray[0];
			$price_min_ttc = $tmparray[2];
		}
	}



	// on ajoute le nouveau prix produit
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_price (fk_product, price_level, date_price';
	$sql.= ' , price, price_ttc, price_min, price_min_ttc, recuperableonly';
	$sql.= ' , localtax1_tx, localtax1_type, localtax2_tx, localtax2_type, fk_price_expression';
	$sql.= ' , tosell, price_by_qty, price_base_type, tva_tx';
	$sql.= ' , fk_user_author';
	$sql.= " ) VALUES (";

	$sql.= $productid.", 1 , now()";
	$sql.= ", ".$newprice.", ".$price_ttc.", ".$price_min.", ".$price_min_ttc.', '.$obj->recuperableonly;
	$sql.= ' , '.$obj->localtax1_tx.', "'.$obj->localtax1_type.'"';
	$sql.= ' , '.$obj->localtax2_tx.', "'.$obj->localtax2_type.'"';
	$sql.= ', '.($obj->fk_price_expression?$obj->fk_price_expression:'null');
	$sql.= ' , '.$obj->tosell.', '.$obj->price_by_qty.' , "'.$obj->price_base_type.'", '.$obj->tva_tx;
	$sql.= ' , '.$user->id;
	$sql.= ")";	
//print $sql."<br>";
	$resqlprod=$db->query($sql);

	// et un update du prix du produit
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'product';
	$sql.= ' SET price='.$newprice;
	$sql.= ' , price_ttc='.$price_ttc;
	$sql.= ' , price_min='.$price_min;
	$sql.= ' , price_min_ttc='.$price_min_ttc;
	$sql.= ' WHERE rowid = '.$productid;

	$resupdate=$db->query($sql);

//print $sql."<br>";

	$action="";

	$result = $object->fetch($id, $ref);


}



/*
 * View
 */


$productstatic = new Product($db);
$form = new Form($db);

llxHeader("", "", $langs->trans("CardProduct".$product->type));

dol_htmloutput_mesg($mesg);

$head=product_prepare_head($object, $user);
$titre=$langs->trans("CardProduct".$object->type);
$picto=('product');
dol_fiche_head($head, 'factory', $titre, 0, $picto);
$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

if ($id || $ref) {
	if ($result) {
		$bproduit = ($object->isproduct()); 

		dol_banner_tab($object, 'ref', $linkback, ($user->socid?0:1), 'ref');

		print '<table class="border" width="100%">';
		// MultiPrix
		if ($conf->global->PRODUIT_MULTIPRICES) {
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
				for ($i=1; $i<=$conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++) {
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

		// on indique la nature du produit
		print '<tr><td>'.$form->textwithpicto($langs->trans("NatureOfProductShort"), $langs->trans("NatureOfProductDesc")).'</td>';
		print '<td>'.$object->getLibFinished().'</td></tr>';
		

		print '<tr><td>'.$langs->trans("PhysicalStock").'</td>';
		print '<td>'.$object->stock_reel.'</td></tr>';
		print '</table>';
		dol_fiche_end();

		// indique si on a déjà une composition de présente ou pas
		$compositionpresente=0;
		
		$head=factory_product_prepare_head($object, $user);
		$titre=$langs->trans("Factory");
		$picto="factory@factory";
		dol_fiche_head($head, 'computeprice', $titre, 0, $picto);

		// pour connaitre les produits composés du produits
		$prodsfather = $factory->getFather(); //Parent Products

		// pour connaitre les produits composant le produits
		$factory->get_sousproduits_arbo();

		// Number of subproducts
		$prods_arbo = $factory->get_arbo_each_prod();
		// something wrong in recurs, change id of object
		$factory->id = $id;
		

		print load_fiche_titre($langs->trans("FactorisedProductsNumber").' : '.count($prods_arbo), '', '');
		
		// List of subproducts
		if (count($prods_arbo) > 0) {
			print "<br>";
			$compositionpresente=1;
			print '<b>'.$langs->trans("FactoryTableInfo").'</b><BR>';

			print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="computeprice">';
			print '<input type="hidden" name="id" value="'.$id.'">';

			print '<table class="border" >';
			print '<tr class="liste_titre">';
			print '<th class="liste_titre" width=10px></th>';
			print '<th class="liste_titre" width=100px align="left">'.$langs->trans("Ref").'</th>';
			print '<th class="liste_titre" width=200px align="left">'.$langs->trans("Label").'</th>';
			print '<th class="liste_titre" width=50px align="center">'.$langs->trans("QtyNeed").'</th>';
			if (!empty($conf->stock->enabled)) { 	// we display vwap titles
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("UnitPmp").'</th>';
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("CostPmpHT").'</th>';
			} else {
				// we display price as latest purchasing unit price title
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("FactoryUnitHA").'</th>';
				print '<th class="liste_titre" width=100px align="right">'.$langs->trans("FactoryCostHA").'</th>';
			}
			print '<th class="liste_titre" width=100px align="right">'.$langs->trans("FactoryUnitPriceHT").'</th>';
			print '<th class="liste_titre" width=100px align="right">'.$langs->trans("FactorySellingPriceHT").'</th>';
			print '<th class="liste_titre" width=100px align="right">'.$langs->trans("ProfitAmount").'</th>';

			print '</tr>';
			$mntTot=0;
			$pmpTot=0;

			foreach ($prods_arbo as $value) {
				//var_dump($value);
				// verify if product have child then display it after the product name
				$tmpChildArbo=$factory->getChildsArbo($value['id']);
				$nbChildArbo="";
				if (count($tmpChildArbo) > 0) $nbChildArbo=" (".count($tmpChildArbo).")";

				print '<tr><td>';
				print "<a href='#line".$objp->rowid."' onclick=\"$('.detaillignecomposition".$objp->rowid."').toggle();\" >";
				print img_picto("", "edit_add")."</a>";
				print '</td>';
				print '<td align="left">'.$factory->getNomUrlFactory($value['id'], 1, 'index').$nbChildArbo;
				print $factory->PopupProduct($value['id']);
				print '</td>';

				print '<td align="left">';
				print $value['label'].'</td>';
				print '<td align="center">'.$value['nb'];
				if ($value['globalqty'] == 1)
					print "&nbsp;G";
				print '</td>';
				$price=$value['price'];
				$pmp=$value['pmp'];


				print '<td align="right">'.price($pmp).'</td>'; // display else vwap or else latest purchasing price
				print '<td align="right">'.price($pmp*$value['nb']).'</td>'; // display total line
				print '<td align="right">'.price($price).'</td>';
				print '<td align="right">'.price($price*$value['nb']).'</td>';
				print '<td align="right">'.price(($price-$pmp)*$value['nb']).'</td>'; 
				
				
				$mntTot=$mntTot+$price*$value['nb'];
				$pmpTot=$pmpTot+$pmp*$value['nb']; // sub total calculation
				
				print '</tr>';
				print "<tr style='display:none' class='detaillignecomposition".$objp->rowid."'>";
				print '<td colspan=2>'.$langs->trans("Position")." : ".$value['ordercomponent'].'</td>';
				print '<td colspan=8>'.$langs->trans("InfoAnnexes")." : ".$value['description'].'</td>';
				print '</tr>';
				//var_dump($value);
				//print '<pre>'.$productstatic->ref.'</pre>';
				//print $productstatic->getNomUrl(1).'<br>';
				//print $value[0];	// This contains a tr line.
			}
			print '<tr class="liste_total">';
			print '<td colspan=5 align=right >'.$langs->trans("Total").'</td>';
			print '<td align="right" >'.price($pmpTot).'</td>';
			print '<td ></td>';
			print '<td align="right" >'.price($mntTot).'</td>';
			print '<td align="right" >'.price($mntTot-$pmpTot).'</td>';

			print '</tr>';
			if ($action == '' && $bproduit) {
				print '<tr><td  align=right></td>';
				print '<td >';
				$object->fetch($id, $ref);

				if ($user->rights->factory->creer) {
					//Le stock doit étre actif et la nature du produit est manufacturée
					if (!empty($conf->stock->enabled) && $object->finished == '1') {
						if ($compositionpresente) {

							print '<td align=right colspan=2>';
							
							print $langs->trans("MarginCoefPrice")." :&nbsp;&nbsp;";
							print '<input type="hidden" name="mnttot" value="'.$mntTot.'">';
							print '<input type="hidden" name="pmptot" value="'.$pmpTot.'">';

							print '<input type="text" name="coefmargin" size=5 value="'.$computepricedefaultcoef.'">';
							print "&nbsp;&nbsp;&nbsp;";
							print '</td>';
							print '<td align=right>';
							print '<input type="submit" class="butAction" value="'.$langs->trans("Recalc").'">';
							print '</td>';

							// on effectue un calcul par le prix de vente ou d'achat des composants
							if ($conf->global->factory_origincomputeprice == "frombuyprice") {
								$newprice = $mntTot * ($computepricedefaultcoef / 100);
								print '<td colspan=2></td>';
							} else
								$newprice = $pmpTot * ($computepricedefaultcoef / 100);

							print '<td align="right" ><b>'.price($newprice).'</b></td>';
							print '<td align="right" ><b>'.price($newprice - $pmpTot).'</b></td>';

							print '</tr><tr><td colspan=9 align=right>';
							print '<input type="submit" class="butAction" value="'.$langs->trans("GenerateSellPrice").'">';
							print '</td></tr>';
							print '<tr><td colspan=9 align=right>';

							print '<div class="tabsAction" style="margin-bottom:128px;">';
							print '<a class="butAction" href="computeprice.php?action=getdefaultprice&amp;id='.$productid.'">';
							print $langs->trans("GetDefaultPrice").'</a>';
							print '<a class="butAction" href="computeprice.php?action=adjustprice&amp;id='.$productid.'">';
							print $langs->trans("AdjustPrice").'</a>';
							print '</div>';
							
							print '</td></tr>';

							
						}
					} 
				}
				print '</td></tr>';
			}

			print '</table>';
			print '</form>';

		}
	}
}

/* Barre d'action			*/

llxFooter();
$db->close();