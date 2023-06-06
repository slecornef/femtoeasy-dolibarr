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
 *	\file	   htdocs/factory/subcontracting.php
 *	\ingroup	factory
 *	\brief	  Gestion de la soutraitance d'OF
 */


$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';


dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

$langs->load('companies');
$langs->load("factory@factory");

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');

$delivery_date=dol_mktime(
	'23', '59', '59',
	GETPOST("liv_month", 'int'), GETPOST("liv_day", 'int'), GETPOST("liv_year", 'int')
);
$socid = GETPOST('socid', "integer");
$ref_supplier = GETPOST("ref_supplier", 'alpha');
$productfournpriceid = GETPOST("productfournpriceid", "int");
// Security check
if ($user->socid) $socid=$user->socid;
$result = restrictedArea($user, 'factory');

$object = new Factory($db);
$object->fetch($id, $ref);
if (empty($id))
	$id = $object->id;

$product = new Product($db);
$entrepot = new Entrepot($db);

$result = $product->fetch($object->fk_product);
$productid= $object->fk_product;

$result = $entrepot->fetch($object->fk_entrepot);
$entrepotid= $object->fk_entrepot;

/*
 * Actions
 */
if ($action == 'create' ) {

	// on vérifie que le fournisseur ou un prix produit a bien été sélectionné
	if ( $socid > 0 || $productfournpriceid > 0) {
		$unit_price =0;
		// si on a sélectionné un prix fournisseur, il est prioritaire sur le fournisseur
		if ( $productfournpriceid > 0) {
			$sql = "SELECT fk_soc, unitprice, ref_fourn, tva_tx";
			$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price ";
			$sql.= " WHERE rowid=".$productfournpriceid;
			$resql=$db->query($sql);
			if ($resql) {
				if ($db->num_rows($resql)) {
					$objf = $db->fetch_object($resql);

					// récup des infos fournisseurs
					$socid = $objf->fk_soc;
					$ref_supplier =$objf->ref_fourn;
					
					// récup du prix fournisseur
					$unit_price = $objf->unitprice;
					$tva_tx = $objf->tva_tx;
				}
			}
		} else {
			// on met à jour les infos de l'of pour la soustraitance
			$object->fk_soc = $socid;
			$object->ref_supplier = $ref_supplier;
		}
		$object->delivery_date = $delivery_date;
		$object->fk_user_commande = $user->id;
		$object->updateSubcontracting($user);
	
		$objectfournisseur = new Fournisseur($db);
		$objectfournisseur->fetch($socid);

		$objectcf = new CommandeFournisseur($db);
		$objectcf->ref_supplier		= $ref_supplier;
		$objectcf->socid			= $socid;
		$objectcf->delivery_date	= $delivery_date;
		$objectcf->note_private		= '';
		$objectcf->note_public		= '';

		$objectcf->cond_reglement_id = $objectfournisseur->cond_reglement_supplier_id;
		$objectcf->mode_reglement_id = $objectfournisseur->mode_reglement_supplier_id;

		// on fait lien après le create
		$objectcf->origin = "";
		$objectcf->origin_id = 0;

		// on met à jours le prix si besoin
		if ($unit_price > 0 ) {
			$object->lines[0]->subprice = $unit_price;
			$object->total_ht = $unit_price * $object->qty_planned;
			$object->total_tva = $object->total_ht * ($tva_tx/100);
			$object->total_ttc =$object->total_ht + $object->total_tva;
		}
		
		// on récupère la ligne du produit à fabriquer 
		$objectcf->lines = $object->lines;

		$idCmdFourn = $objectcf->create($user);
		$ret = $objectcf->add_object_linked("factory", $id);
	}
}

// on cloture la sous-traitance
if ($action == "closeof") {
	$cmdFournId = GETPOST("cmdfournid", "integer");
	$object->closeSubcontractingOF($cmdFournId);
	
	//$object->fetch($id, $ref);
}
/*
 * View
 */
llxHeader();

$form = new Form($db);

dol_htmloutput_mesg($mesg);

$head=factory_prepare_head($object, $user);
dol_fiche_head($head, 'subcontracting', $langs->trans('Factory'), -1, 'factory@factory');


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

// gestion de la sous soustraitance
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';

// si déjà une sous-traitance ou on n'est plus en brouillon
if ($object->fk_soc >0 || $object->fk_statut !=0) {

	print '<table class="border tableforfield" width="100%">';
	print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
	print $langs->trans("SubContracter");
	print '</th></tr>';

	// Third party
	print '<tr><td width=35% class="fieldrequired">'.$langs->trans('Supplier').'</td>';
	print '<td>';
	if ($object->fk_soc) {
		$object->fetch_thirdparty();
		print $object->thirdparty->getNomUrl(1);
	}
	print '</td>';	
	// Ref supplier
	print '<tr><td>'.$langs->trans('RefSupplier').'</td>';
	print '<td>';
	print $object->ref_supplier;
	print '</td></tr>';
	print '</tr>';
	
	// Planned delivery date
	print '<tr><td>';
	print $langs->trans('DateDeliveryPlanned');
	print '</td>';
	print '<td>';
	print dol_print_date($object->delivery_date, 'day');
	print '</td></tr>';
	print '</table>';
	
	print '</div>';
	print '<div class="fichehalfright"><div class="ficheaddleft">';
	$supplier_orderId = 0;
	// on récupère les infos de la commande fournisseur en lien
	$sql = "SELECT el.fk_target";
	$sql .= " FROM ".MAIN_DB_PREFIX."element_element as el";
	$sql .= " WHERE el.fk_source = ".$id;
	$sql .= " AND el.sourcetype = 'factory'";
	$sql .= " AND el.targettype = 'order_supplier'";
	$resql=$db->query($sql);
	if ($resql) {
		if ($db->num_rows($resql)) {
			$obj = $db->fetch_object($resql);
			$supplier_orderId = $obj->fk_target;
		}
	}

	
	if ($supplier_orderId > 0) {
		$objectcf = new CommandeFournisseur($db);
		$objectcf->fetch($supplier_orderId);
		print '<table class="border tableforfield" width="100%">';
		print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
		print $langs->trans("SupplierOrderInfos");
		print '</th></tr>';
		print '<tr ><td align=left width=35%>';
		print $langs->trans("SupplierOrder");
		print '</td><td>';
		print $objectcf->getNomUrl(1);
		print '</td></tr>';

		// Planned delivery date
		print '<tr><td>';
		print $langs->trans('DateSupplierOrder');
		print '</td>';
		print '<td>';
		print dol_print_date($objectcf->date_commande, 'day');
		print '</td></tr>';
		
		// Planned delivery date
		print '<tr><td>';
		print $langs->trans('DateApprove');
		print '</td>';
		print '<td>';
		print dol_print_date($objectcf->date_approve, 'day');
		print '</td></tr>';

		print '<tr><td>';
		print $langs->trans('Statut');
		print '</td>';
		print '<td>';
		print $objectcf->getLibStatut(5);
		print '</td></tr>';

		print '</table>';
		// si la commande a été traité et que l'OF est sous-traité
		if ($object->fk_statut == 4 && $objectcf->fk_statut ==5) {
			print '<div class="tabsAction">';
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=closeof&cmdfournid='.$supplier_orderId.'">'.$langs->trans('closeOF').'</a>';
			print '</div>';	
		}
	
	}
	print '</div>';

} else {
	// on accède directement à la création de la sous traitance
	print '<form action="subcontracting.php" method="post" name="formulaire">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="create">';
	print '<input type="hidden" name="id" value="'.$id.'">';

	print '<table class="border tableforfield" width="100%">';
	// Third party
	// a-t-on des prix fournisseurs pour ce produit?
	$sql = "SELECT count(*) as nb";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp ON p.rowid = pfp.fk_product";
	$sql .= " WHERE pfp.entity IN (".getEntity('productsupplierprice').")";
	$sql .= " AND pfp.fk_product = ".$object->fk_product;
	$result = $db->query($sql);
	$num=0;
	if ($result) 
		$num = $db->num_rows($result);

	// si pas de prix fournisseur, on doit saisir un fournisseur
	print '<tr><td width=25% '.($num==0?'class="fieldrequired"':'').'>'.$langs->trans('Supplier').'</td>';
	print '<td>';
	print img_picto('', 'company').$form->select_company((empty($socid) ? '' : $socid), 'socid', 's.fournisseur=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
	print '</td></tr>';	

	if ($num == 0) {
		// pas de prix fournisseur sélectionné car il n'y en a pas
		// on affiche le prix d'achat retenu selon le paramétrage
		print '<tr><td >'.$langs->trans('CostPrice').'</td>';
		print '<td>';
		print img_picto('', 'product');
		
		print '</td></tr>';	

		print "<input type=hidden name='productfournpriceid' value='-1'>";
	} else {
		print '<tr><td >'.$langs->trans('SupplierProductPrice').'</td>';
		print '<td>';
		print img_picto('', 'product').$form->select_product_fourn_price($object->fk_product, 'productfournpriceid','');
		print '</td></tr>';	
	}

	// Ref supplier
	print '<tr><td>'.$langs->trans('RefSupplier').'</td><td><input name="ref_supplier" type="text"></td>';
	print '</tr>';
	
	// Planned delivery date
	print '<tr><td>';
	print $langs->trans('DateDeliveryPlanned');
	print '</td>';
	print '<td>';
	print $form->selectDate($delivery_date ? $delivery_date : -1, 'liv_', 0, 0, '', "set");
	print '</td></tr>';
	print '</table>';	
	print '</div>';
	print '<div class="clearboth"></div>';
	print '<div class="tabsAction">';
	print ' <input type="submit" class="butAction" value="'.$langs->trans("Update").'">';
	print ' <input type="submit" class="butAction" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';	
	print '</form>';

}
print '</div>';
print '</div></div>';
print '<div style="clear:both"></div>';

print "</div>";
llxFooter();
$db->close();