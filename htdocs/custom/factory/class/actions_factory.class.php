<?php
/* Copyright (C) 2014-2022		Charlene Benke	<charlene@patas-monkey.com>
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
 * 	\file	   htdocs/customlink/class/actions_customlink.class.php
 * 	\ingroup	customlink
 * 	\brief	  Fichier de la classe des actions/hooks des customlink
 */

class ActionsFactory 
{

	function loadvirtualstock($parameters, $object, $action) 
	{
		global $conf, $db;
		if (!empty($conf->global->FACTORY_AddVirtualstock)) {
			// si c'est un produit utilisé dans la composition
			$sql = "SELECT SUM(fd.qty_planned) as qty";
			$sql.= " FROM ".MAIN_DB_PREFIX."factorydet as fd";
			$sql.= ", ".MAIN_DB_PREFIX."factory as f";
			$sql.= " WHERE f.rowid = fd.fk_factory";
			$sql.= " AND f.entity IN (".getEntity('factory').")";
			$sql.= " AND f.fk_statut = 1"; // seulement sur les of encours
			$sql.= " AND fd.fk_product = ".$parameters['id'];

			$result = $db->query($sql);
			if ( $result ) {
				$obj=$db->fetch_object($result);
				$object->stock_theorique-=$obj->qty;

			}

			// si c'est un produit en cours de fabrication
			$sql = "SELECT SUM(f.qty_planned) as qty";
			$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
			$sql.= " WHERE f.entity IN (".getEntity('factory').")";
			$sql.= " AND f.fk_statut = 1"; // seulement sur les of encours
			$sql.= " AND f.fk_product = ".$parameters['id'];
			$result = $db->query($sql);
			if ( $result ) {
				$obj=$db->fetch_object($result);
				$object->stock_theorique-=$obj->qty;
			}
			$this->results= array("stock_theorique" => $object->stock_theorique);
		}
		return 0;
	}

	function doMassActions($parameters, $object, $action) 
	{
		// à voir plus tard
		if (!$error && $massaction == 'cancel' && $object->element == "factory" ) {

		}
	}

	function addSearchEntry ($parameters, $object, $action) 
	{
		global $confg, $langs;
		$resArray=array();
		$resArray['searchintofactory']=array(
			'position'=>31, 'img'=>'object_factory@factory', 
			'label'=>$langs->trans("Factory"),
			'text'=>img_picto('','object_factory@factory').' '.$langs->trans("Factory", GETPOST('q')), 
			'url'=>dol_buildpath('/factory/list.php?sall='.urlencode(GETPOST('q')), 1)
		);

		
		$this->results = $resArray;
		return 0;
	}

	function printElementTab($parameters, $object, $action) 
	{
		global $db, $langs, $form, $user;

		$element = $parameters['element'];
		$element_id = $parameters['element_id'];

		if ($element == 'factory') {
			dol_include_once('/factory/class/factory.class.php');
			dol_include_once('/factory/core/lib/factory.lib.php');

			$factorystatic = new Factory($db);
			$factorystatic->fetch($element_id);

			if ($user->socid > 0) $socid=$user->socid;
			$result = restrictedArea($user, 'factory', $id);

			$head = factory_prepare_head($factorystatic);
			dol_fiche_head($head, 'resource', $langs->trans("Factory"), 0, 'factory@factory');
			print '<table class="border" width="100%">';
			$linkback = '<a href="'.dol_buildpath('/factory/list.php', 1).'">'.$langs->trans("BackToList").'</a>';

			// Ref
			print '<tr><td width="30%">'.$langs->trans('Ref').'</td><td colspan="3">';
			print $form->showrefnav($factorystatic, 'ref', $linkback, 1, 'ref', 'ref', '');
			print '</td></tr>';

			// Label
			print '<tr><td>'.$langs->trans("Label").'</td><td>'.$factorystatic->title.'</td></tr>';

			print "</table>";
			dol_fiche_end();
		}
		return 0;
	}

	function dashboardMRP($parameters, $object, $action)
	{
		dol_include_once('/factory/class/factory.class.php');
		global $db, $langs;
		$factory_static = new Factory($db);
		print "<div class='fichetwothirdright'>";
		/*
		* Last modified OF
		*/
		$max=5;
		$sql = "SELECT f.rowid, f.ref, p.ref as refproduit, f.datec, f.qty_planned, f.qty_made, p.label,";
		$sql.= " f.tms as datem, f.fk_statut";
		$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p on f.fk_product = p.rowid";
		$sql.= " WHERE f.entity IN (".getEntity("product", 1).")";
		$sql.= $db->order("f.datec", "DESC");
		$sql.= $db->plimit($max, 0);

		//print $sql;
		$result = $db->query($sql);
		if ($result) {
			$num = $db->num_rows($result);
			$i = 0;
			if ($num > 0) {
				$lastModifiedFactory = $langs->trans("LastFactory", $max);
				print '<table class="noborder" width="100%">';
				$colnb=5;
				print '<tr class="liste_titre"><td colspan="'.$colnb.'">'.$lastModifiedFactory.'</td></tr>';
				while ($i < $num) {
					$objp = $db->fetch_object($result);
					print "<tr>";
					print '<td nowrap="nowrap">';
					$factory_static->fetch($objp->rowid);
					//$equipement_static->ref=$objp->ref;
					//$equipement_static->fk_product=$objp->refproduit;
					print $factory_static->getNomUrl(1);
					print "</td>\n";

					print '<td>'.$objp->refproduit. " - ".dol_trunc($objp->label, 32).'</td>';
					print "<td align=right>".price($objp->qty_made?$objp->qty_made:$objp->qty_planned, 0, '', 0, 0, 0)."</td>";
					print "<td align=center>".dol_print_date($db->jdate($objp->datem), 'day')."</td>";
					print "<td align=right>".$factory_static->LibStatut($objp->fk_statut, 5)."</td>";
					print "</tr>\n";
					$i++;
				}
				print "</table>";
			}
		} else
			dol_print_error($db);
		print "</div>";
	}
	
}