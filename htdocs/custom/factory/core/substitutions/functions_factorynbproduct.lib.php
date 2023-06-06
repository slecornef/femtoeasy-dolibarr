<?php
/* Copyright (C) 2014-2022	Charlene BENKE	<charlene@patas-monkey.com>
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
 * or see http://www.gnu.org/
 */

/*		Function called to complete substitution array (before generating on ODT, or a personalized email)
 *		functions xxx_completesubstitutionarray are called by make_substitutions() if file
 *		is inside directory htdocs/core/substitutions
 *
 *		@param	array		$substitutionarray	Array with substitution key=>val
 *		@param	Translate	$langs				Output langs
 *		@param	Object		$object				Object to use to get values
 *		@return	void		The entry parameter $substitutionarray is modified
 */
function factorynbproduct_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters)
{
	global $db;
	$infoAdd="";
	$nbToBuild =0;

	if ($object == null) 
		return 0;

	// détermine si il y une composition
	$sql="SELECT count(distinct fk_product_children) AS nb FROM ".MAIN_DB_PREFIX."product_factory";
	$sql.=" WHERE fk_product_father = ".$object->id;

	$result = $db->query($sql);
	if ($result) {
		if ($db->num_rows($result)) {
			$obj = $db->fetch_object($result);
			$nbToBuild = $obj->nb;
		}
	}
	if ($nbToBuild)
		$infoAdd= '<span style="background-color:orange;" class="badge">'.$nbToBuild.'</span>';

	$substitutionarray['factorynbproduct']="Factory".$infoAdd;
}
